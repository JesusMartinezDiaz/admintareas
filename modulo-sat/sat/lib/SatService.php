<?php
/**
 * Cliente SAT Descarga Masiva CFDI v1.5 compatible con PHP 7.1.x.
 *
 * No usa Composer. Requiere: curl, openssl, dom, libxml, zip, simplexml.
 * Implementa autenticación WS-Security, solicitud, verificación y descarga.
 */
class SatService
{
    const BUILD = '2026-10-SAT-MODULO-V2';

    /** Tokens vigentes por RFC+serie (el SAT los emite con vigencia de 5 minutos). */
    private static $tokenCache = array();
    /** Códigos cURL que se consideran fallas de red transitorias y se reintentan. */
    private static $curlTransitorios = array(6, 7, 28, 35, 52, 55, 56);
    const NS_DES = 'http://DescargaMasivaTerceros.sat.gob.mx';
    const NS_DS  = 'http://www.w3.org/2000/09/xmldsig#';
    const NS_SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';

    const URL_AUTH = 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/Autenticacion/Autenticacion.svc';
    const URL_QUERY = 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/SolicitaDescargaService.svc';
    const URL_VERIFY = 'https://cfdidescargamasivasolicitud.clouda.sat.gob.mx/VerificaSolicitudDescargaService.svc';
    const URL_DOWNLOAD = 'https://cfdidescargamasiva.clouda.sat.gob.mx/DescargaMasivaService.svc';

    /**
     * @param array $filtros Opcional: tipo_comprobante (I,E,P,N,T) y estado_comprobante
     *                       (Vigente/Cancelado, solo emitidas o metadata).
     */
    public static function solicitarDescarga($empresa, $certificado, $tipoDescarga, $tipoArchivo, $fechaInicial, $fechaFinal, $filtros = array())
    {
        $ctx = self::credentialContext($empresa, $certificado);
        $token = self::authenticate($ctx);

        $tipoDescarga = strtoupper(trim($tipoDescarga));
        $tipoArchivo = strtoupper(trim($tipoArchivo));
        if (!in_array($tipoDescarga, array('EMITIDAS', 'RECIBIDAS'), true)) {
            throw new Exception('Tipo de descarga no válido.');
        }
        if (!in_array($tipoArchivo, array('CFDI', 'METADATA'), true)) {
            throw new Exception('Tipo de archivo no válido.');
        }
        // El SAT espera exactamente CFDI o Metadata (respeta mayúsculas/minúsculas).
        $tipoSolicitudSat = ($tipoArchivo === 'CFDI') ? 'CFDI' : 'Metadata';

        $fi = self::dateStart($fechaInicial);
        $ff = self::dateEnd($fechaFinal);
        if (strtotime($fi) >= strtotime($ff)) {
            throw new Exception('La fecha inicial debe ser menor que la fecha final.');
        }

        if ($tipoDescarga === 'EMITIDAS') {
            $operation = 'SolicitaDescargaEmitidos';
            $action = self::NS_DES . '/ISolicitaDescargaService/SolicitaDescargaEmitidos';
            // Para emitidos no enviamos EstadoComprobante si no se solicitó un filtro.
            $attrs = array(
                'FechaInicial' => $fi,
                'FechaFinal' => $ff,
                'RfcEmisor' => $ctx['rfc'],
                'RfcSolicitante' => $ctx['rfc'],
                'TipoSolicitud' => $tipoSolicitudSat
            );
        } else {
            $operation = 'SolicitaDescargaRecibidos';
            $action = self::NS_DES . '/ISolicitaDescargaService/SolicitaDescargaRecibidos';
            $attrs = array(
                'FechaInicial' => $fi,
                'FechaFinal' => $ff,
                'RfcReceptor' => $ctx['rfc'],
                'RfcSolicitante' => $ctx['rfc'],
                'TipoSolicitud' => $tipoSolicitudSat
            );
            // En v1.5, RECIBIDOS + XML requiere expresamente comprobantes vigentes.
            if ($tipoArchivo === 'CFDI') {
                $attrs['EstadoComprobante'] = 'Vigente';
            }
        }

        $tipoComprobante = isset($filtros['tipo_comprobante']) ? strtoupper(trim((string)$filtros['tipo_comprobante'])) : '';
        if ($tipoComprobante !== '') {
            if (!in_array($tipoComprobante, array('I', 'E', 'P', 'N', 'T'), true)) {
                throw new Exception('Tipo de comprobante no válido: ' . $tipoComprobante);
            }
            $attrs['TipoComprobante'] = $tipoComprobante;
        }
        $estadoComprobante = isset($filtros['estado_comprobante']) ? trim((string)$filtros['estado_comprobante']) : '';
        if ($estadoComprobante !== '' && !isset($attrs['EstadoComprobante'])) {
            $attrs['EstadoComprobante'] = $estadoComprobante;
        }

        $body = self::buildSignedOperation($operation, 'solicitud', $attrs, $ctx, null);
        $envelope = self::soapEnvelope($body);
        $response = self::httpSoap(self::URL_QUERY, $action, $envelope, $token);
        self::throwSoapFault($response['body']);

        // El SAT ha respondido con variantes de capitalización según la operación.
        $result = self::firstElementByLocalNames($response['body'], array(
            $operation . 'Result',
            lcfirst($operation) . 'Result',
            'SolicitaDescargaResult',
            'solicitaDescargaResult'
        ));
        if (!$result) {
            throw new Exception('El SAT respondió sin un resultado de solicitud. HTTP ' . $response['http_code'] . '. Respuesta: ' . self::shortText($response['body']));
        }

        $requestId = self::xmlAttr($result, 'IdSolicitud');
        $cod = self::xmlAttr($result, 'CodEstatus');
        $msg = self::xmlAttr($result, 'Mensaje');
        $ok = ($requestId !== '');

        return array(
            'ok' => $ok,
            'request_id' => $requestId,
            'codigo_estado' => $cod,
            'mensaje' => $msg !== '' ? $msg : ($ok ? 'Solicitud aceptada por el SAT.' : 'Solicitud rechazada por el SAT.'),
            'raw_response' => $response['body']
        );
    }

    public static function verificarSolicitud($empresa, $certificado, $requestId)
    {
        if (!trim($requestId)) {
            throw new Exception('La solicitud no tiene Request ID del SAT.');
        }
        $ctx = self::credentialContext($empresa, $certificado);
        $token = self::authenticate($ctx);
        $attrs = array(
            'IdSolicitud' => trim($requestId),
            'RfcSolicitante' => $ctx['rfc']
        );
        $operation = 'VerificaSolicitudDescarga';
        $body = self::buildSignedOperation($operation, 'solicitud', $attrs, $ctx, null);
        $envelope = self::soapEnvelope($body);
        $action = self::NS_DES . '/IVerificaSolicitudDescargaService/VerificaSolicitudDescarga';
        $response = self::httpSoap(self::URL_VERIFY, $action, $envelope, $token);
        self::throwSoapFault($response['body']);

        $result = self::firstElementByLocalName($response['body'], 'VerificaSolicitudDescargaResult');
        if (!$result) {
            throw new Exception('El SAT respondió sin resultado de verificación. HTTP ' . $response['http_code'] . '. Respuesta: ' . self::shortText($response['body']));
        }

        $estadoNum = self::xmlAttr($result, 'EstadoSolicitud');
        $cod = self::xmlAttr($result, 'CodigoEstadoSolicitud');
        if ($cod === '') {
            $cod = self::xmlAttr($result, 'CodEstatus');
        }
        $msg = self::xmlAttr($result, 'Mensaje');
        $total = (int) self::xmlAttr($result, 'NumeroCFDIs');
        $estado = self::estadoSolicitudLabel($estadoNum);
        $esErrorTemporalSat = ((string)$estadoNum === '0' && in_array((string)$cod, array('404','5006'), true));

        $paquetes = array();
        $dom = self::loadXml($response['body']);
        $xp = new DOMXPath($dom);
        $nodes = $xp->query('//*[local-name()="IdsPaquetes"]');
        if ($nodes) {
            foreach ($nodes as $n) {
                $v = trim($n->textContent);
                if ($v !== '' && !in_array($v, $paquetes, true)) {
                    $paquetes[] = $v;
                }
            }
        }

        return array(
            'ok' => true,
            'estado_solicitud' => $estado,
            'estado_numero' => $estadoNum,
            'codigo_estado' => $cod,
            'mensaje' => $msg !== '' ? $msg : $estado,
            'total_cfdi' => $total,
            'paquetes' => $paquetes,
            'error_temporal_sat' => $esErrorTemporalSat,
            'raw_response' => $response['body']
        );
    }

    public static function descargarPaquete($empresa, $certificado, $idPaqueteSat)
    {
        if (!trim($idPaqueteSat)) {
            throw new Exception('IdPaquete vacío.');
        }
        $ctx = self::credentialContext($empresa, $certificado);
        $token = self::authenticate($ctx);
        $attrs = array(
            'IdPaquete' => trim($idPaqueteSat),
            'RfcSolicitante' => $ctx['rfc']
        );
        $operation = 'PeticionDescargaMasivaTercerosEntrada';
        $body = self::buildSignedOperation($operation, 'peticionDescarga', $attrs, $ctx, null);
        $envelope = self::soapEnvelope($body);
        $action = self::NS_DES . '/IDescargaMasivaTercerosService/Descargar';
        $response = self::httpSoap(self::URL_DOWNLOAD, $action, $envelope, $token);
        self::throwSoapFault($response['body']);

        // La respuesta de descarga trae el estatus en Header/respuesta y el ZIP en Paquete.
        $respuesta = self::firstElementByLocalNames($response['body'], array('respuesta', 'Respuesta'));
        if ($respuesta) {
            $codStatus = self::xmlAttr($respuesta, 'CodEstatus');
            if ($codStatus === '') $codStatus = self::xmlAttr($respuesta, 'codestatus');
            $msgStatus = self::xmlAttr($respuesta, 'Mensaje');
            if ($msgStatus === '') $msgStatus = self::xmlAttr($respuesta, 'mensaje');
            if ($codStatus !== '' && $codStatus !== '5000') {
                throw new Exception('SAT rechazó la descarga del paquete (' . $codStatus . '): ' . $msgStatus);
            }
        }
        $result = self::firstElementByLocalNames($response['body'], array('Paquete', 'paquete', 'DescargarResult'));
        if (!$result) {
            throw new Exception('El SAT no devolvió contenido de paquete. HTTP ' . $response['http_code'] . '. Respuesta: ' . self::shortText($response['body']));
        }
        $base64 = trim($result->textContent);
        $binary = base64_decode($base64, true);
        if ($binary === false || strlen($binary) < 4) {
            throw new Exception('El paquete SAT no contiene un ZIP válido.');
        }
        return array('ok' => true, 'zip_binary' => $binary, 'raw_response' => $response['body']);
    }

    public static function probarCredenciales($empresa, $certificado)
    {
        $ctx = self::credentialContext($empresa, $certificado);
        $token = self::authenticate($ctx);
        return array(
            'ok' => true,
            'rfc' => $ctx['rfc'],
            'serial' => $ctx['serial'],
            'vigencia_desde' => $ctx['valid_from'],
            'vigencia_hasta' => $ctx['valid_to'],
            'token_preview' => substr($token, 0, 40) . '...'
        );
    }

    private static function credentialContext($empresa, $certificado)
    {
        if (!function_exists('openssl_pkey_get_private')) {
            throw new Exception('La extensión OpenSSL no está habilitada en PHP.');
        }
        if (!function_exists('curl_init')) {
            throw new Exception('La extensión cURL no está habilitada en PHP.');
        }
        if (!class_exists('DOMDocument')) {
            throw new Exception('La extensión DOM/XML no está habilitada en PHP.');
        }

        $cerPath = self::resolvePath($certificado['archivo_cer']);
        $keyPath = self::resolvePath($certificado['archivo_key']);
        if (!is_file($cerPath)) throw new Exception('No se encuentra el archivo CER: ' . $cerPath);
        if (!is_file($keyPath)) throw new Exception('No se encuentra el archivo KEY: ' . $keyPath);

        $password = decrypt_password_key($certificado['password_key']);
        if ($password === false || $password === null) {
            throw new Exception('No se pudo descifrar la contraseña de la e.firma. Revisa SAT_SECRET_KEY.');
        }

        $cerDer = file_get_contents($cerPath);
        $keyDer = file_get_contents($keyPath);
        $cerPem = self::pemWrap($cerDer, 'CERTIFICATE');
        $keyPem = self::pemWrap($keyDer, 'ENCRYPTED PRIVATE KEY');

        $privateKey = @openssl_pkey_get_private($keyPem, $password);
        if (!$privateKey) {
            // Algunos archivos ya están en PEM.
            $privateKey = @openssl_pkey_get_private($keyDer, $password);
        }
        if (!$privateKey) {
            throw new Exception('No se pudo abrir la llave privada. Verifica que sea la KEY de la e.firma y que la contraseña sea correcta. OpenSSL: ' . self::opensslErrors());
        }

        $certResource = @openssl_x509_read($cerPem);
        if (!$certResource) {
            throw new Exception('No se pudo leer el certificado CER. OpenSSL: ' . self::opensslErrors());
        }
        $parsed = openssl_x509_parse($certResource);
        if (!$parsed) throw new Exception('No se pudo interpretar el certificado CER.');

        $rfcEmpresa = strtoupper(trim($empresa['rfc']));
        $rfcCert = self::extractRfcFromCertificate($parsed);
        if ($rfcCert !== '' && $rfcEmpresa !== '' && $rfcCert !== $rfcEmpresa) {
            throw new Exception('El RFC del certificado (' . $rfcCert . ') no coincide con la empresa (' . $rfcEmpresa . ').');
        }

        return array(
            'rfc' => $rfcEmpresa,
            'cer_der' => $cerDer,
            'cer_base64' => base64_encode($cerDer),
            'cer_pem' => $cerPem,
            'private_key' => $privateKey,
            'issuer' => self::issuerString($parsed),
            'serial' => self::serialDecimal($parsed),
            'valid_from' => isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', $parsed['validFrom_time_t']) : '',
            'valid_to' => isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : ''
        );
    }

    private static function authenticate($ctx)
    {
        $cacheKey = $ctx['rfc'] . '|' . $ctx['serial'];
        if (isset(self::$tokenCache[$cacheKey]) && self::$tokenCache[$cacheKey]['expira'] > time()) {
            return self::$tokenCache[$cacheKey]['token'];
        }
        $token = self::authenticateSat($ctx);
        // Se reutiliza 4 minutos para no rozar la expiración de 5 minutos del SAT.
        self::$tokenCache[$cacheKey] = array('token' => $token, 'expira' => time() + 240);
        return $token;
    }

    private static function authenticateSat($ctx)
    {
        /*
         * IMPORTANTE:
         * Esta implementación replica la estructura que usa actualmente
         * phpcfdi/sat-ws-descarga-masiva para autenticarse contra SAT.
         * NO agrega ActivityId, To ni Action dentro del XML SOAP.
         */
        $tokenId = 'uuid-' . self::uuidV4() . '-1';
        $now = microtime(true);
        $created = self::utcIso($now);
        $expires = self::utcIso($now + 300);

        $nsWsu = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd';
        $nsWsse = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
        $authNs = 'http://DescargaMasivaTerceros.gob.mx';
        $action = $authNs . '/IAutenticacion/Autentica';

        /*
         * El digest del Timestamp NO lo calculamos desde DOMDocument.
         * Se calcula exactamente sobre el XML compacto que firma la
         * implementación de referencia de phpCfdi.
         */
        $timestampToDigest = '<u:Timestamp xmlns:u="' . $nsWsu . '" u:Id="_0">'
            . '<u:Created>' . $created . '</u:Created>'
            . '<u:Expires>' . $expires . '</u:Expires>'
            . '</u:Timestamp>';
        $timestampToDigest = self::noSpaces($timestampToDigest);
        $digestValue = base64_encode(sha1($timestampToDigest, true));

        $signedInfo = self::createSignedInfo($digestValue, '#_0');
        $signatureBytes = '';
        if (!openssl_sign($signedInfo, $signatureBytes, $ctx['private_key'], OPENSSL_ALGO_SHA1)) {
            throw new Exception('No se pudo firmar la autenticacion SAT: ' . self::opensslErrors());
        }
        $signatureValue = base64_encode($signatureBytes);

        /*
         * SignedInfo se firma incluyendo xmlns=dsig, pero al insertarlo dentro
         * de Signature (que ya tiene ese namespace por defecto), se remueve
         * xmlns de SignedInfo. Es exactamente el comportamiento de phpCfdi.
         */
        $signedInfoEmbedded = str_replace(
            '<SignedInfo xmlns="' . self::NS_DS . '">',
            '<SignedInfo>',
            $signedInfo
        );

        $keyInfo = '<KeyInfo>'
            . '<o:SecurityTokenReference>'
            . '<o:Reference URI="#' . $tokenId . '" '
            . 'ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3"/>'
            . '</o:SecurityTokenReference>'
            . '</KeyInfo>';

        $signature = '<Signature xmlns="' . self::NS_DS . '">' .
            $signedInfoEmbedded .
            '<SignatureValue>' . $signatureValue . '</SignatureValue>' .
            $keyInfo .
            '</Signature>';

        $xml = '<s:Envelope xmlns:s="' . self::NS_SOAP . '" xmlns:u="' . $nsWsu . '">' .
            '<s:Header>' .
                '<o:Security xmlns:o="' . $nsWsse . '" s:mustUnderstand="1">' .
                    '<u:Timestamp u:Id="_0">' .
                        '<u:Created>' . $created . '</u:Created>' .
                        '<u:Expires>' . $expires . '</u:Expires>' .
                    '</u:Timestamp>' .
                    '<o:BinarySecurityToken u:Id="' . $tokenId . '" '
                        . 'ValueType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-x509-token-profile-1.0#X509v3" '
                        . 'EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'
                        . $ctx['cer_base64'] .
                    '</o:BinarySecurityToken>' .
                    $signature .
                '</o:Security>' .
            '</s:Header>' .
            '<s:Body><Autentica xmlns="' . $authNs . '"/></s:Body>' .
            '</s:Envelope>';
        $xml = self::noSpaces($xml);

        $response = self::httpSoap(self::URL_AUTH, $action, $xml, null);
        self::throwSoapFault($response['body']);

        $result = self::firstElementByLocalName($response['body'], 'AutenticaResult');
        if (!$result) {
            throw new Exception(
                'El SAT no devolvio token de autenticacion. HTTP ' .
                $response['http_code'] . '. Respuesta: ' . self::shortText($response['body'])
            );
        }

        $token = trim($result->textContent);
        if ($token === '') {
            throw new Exception('El token de autenticacion regreso vacio.');
        }
        return $token;
    }

    private static function buildSignedOperation($operation, $requestNode, $attrs, $ctx, $children)
    {
        /*
         * Replica el FielRequestBuilder actual de phpCfdi:
         * - elimina atributos vacíos
         * - ordena atributos alfabéticamente
         * - digest SHA1 del XML compacto
         * - Exclusive C14N declarado en SignedInfo
         * - firma RSA-SHA1
         */
        $clean = array();
        foreach ($attrs as $k => $v) {
            if ($v !== null && $v !== '') {
                $clean[$k] = (string) $v;
            }
        }
        ksort($clean);

        $attrText = array();
        foreach ($clean as $k => $v) {
            $attrText[] = self::xmlEscape($k) . '="' . self::xmlEscape($v) . '"';
        }
        $attrText = implode(' ', $attrText);

        $extra = '';
        if (is_array($children)) {
            foreach ($children as $childXml) {
                $extra .= $childXml;
            }
        }

        $toDigest = '<des:' . $operation . ' xmlns:des="' . self::NS_DES . '">' .
            '<des:' . $requestNode . ($attrText !== '' ? ' ' . $attrText : '') . '>' .
            $extra .
            '</des:' . $requestNode . '>' .
            '</des:' . $operation . '>';
        $toDigest = self::noSpaces($toDigest);

        $digest = base64_encode(sha1($toDigest, true));
        $signedInfo = self::createSignedInfo($digest, '');
        $signatureBytes = '';
        if (!openssl_sign($signedInfo, $signatureBytes, $ctx['private_key'], OPENSSL_ALGO_SHA1)) {
            throw new Exception('No se pudo firmar la solicitud SAT: ' . self::opensslErrors());
        }
        $signatureValue = base64_encode($signatureBytes);
        $signedInfoEmbedded = str_replace(
            '<SignedInfo xmlns="' . self::NS_DS . '">',
            '<SignedInfo>',
            $signedInfo
        );

        $keyInfo = '<KeyInfo><X509Data><X509IssuerSerial>' .
            '<X509IssuerName>' . self::xmlEscape($ctx['issuer']) . '</X509IssuerName>' .
            '<X509SerialNumber>' . self::xmlEscape($ctx['serial']) . '</X509SerialNumber>' .
            '</X509IssuerSerial>' .
            '<X509Certificate>' . $ctx['cer_base64'] . '</X509Certificate>' .
            '</X509Data></KeyInfo>';

        $signature = '<Signature xmlns="' . self::NS_DS . '">' .
            $signedInfoEmbedded .
            '<SignatureValue>' . $signatureValue . '</SignatureValue>' .
            $keyInfo .
            '</Signature>';

        return '<des:' . $operation . '>' .
            '<des:' . $requestNode . ($attrText !== '' ? ' ' . $attrText : '') . '>' .
            $extra .
            $signature .
            '</des:' . $requestNode . '>' .
            '</des:' . $operation . '>';
    }

    private static function createSignedInfo($digestValue, $uri)
    {
        $xml = '<SignedInfo xmlns="' . self::NS_DS . '">' .
            '<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></CanonicalizationMethod>' .
            '<SignatureMethod Algorithm="http://www.w3.org/2000/09/xmldsig#rsa-sha1"></SignatureMethod>' .
            '<Reference URI="' . self::xmlEscape($uri) . '">' .
                '<Transforms>' .
                    '<Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"></Transform>' .
                '</Transforms>' .
                '<DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"></DigestMethod>' .
                '<DigestValue>' . $digestValue . '</DigestValue>' .
            '</Reference>' .
            '</SignedInfo>';
        return self::noSpaces($xml);
    }

    private static function noSpaces($xml)
    {
        return preg_replace('/>\s+</', '><', trim($xml));
    }

    private static function xmlEscape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private static function soapEnvelope($bodyXml)
    {
        return '<s:Envelope xmlns:s="' . self::NS_SOAP . '" xmlns:des="' . self::NS_DES . '" xmlns:xd="' . self::NS_DS . '">' .
            '<s:Header/><s:Body>' . $bodyXml . '</s:Body></s:Envelope>';
    }

    private static function httpSoap($url, $soapAction, $xml, $token)
    {
        $intento = 0;
        while (true) {
            $intento++;
            try {
                return self::httpSoapOnce($url, $soapAction, $xml, $token);
            } catch (SatNetworkException $e) {
                if ($intento >= 3) {
                    throw new Exception($e->getMessage());
                }
                sleep(2 * $intento);
            }
        }
    }

    private static function httpSoapOnce($url, $soapAction, $xml, $token)
    {
        $headers = array(
            'Content-Type: text/xml; charset="utf-8"',
            'Accept: text/xml',
            'Cache-Control: no-cache',
            'SOAPAction: ' . $soapAction,
            'Expect:'
        );
        if ($token !== null && $token !== '') {
            $headers[] = 'Authorization: WRAP access_token="' . $token . '"';
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        // El endpoint WCF del SAT espera HTTP/1.1. Algunos cURL modernos negocian
        // HTTP/2 por ALPN y el servicio puede responder 400 sin cuerpo.
        if (defined('CURL_HTTP_VERSION_1_1')) {
            curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ConectorSAT-PHP/' . self::BUILD);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $errno = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // Diagnóstico opcional (config 'debug_soap'). El token se enmascara y
        // nunca se escribe la llave privada ni la contraseña.
        if (function_exists('sat_config') && sat_config('debug_soap')) {
            $debugDir = sat_path_logs();
            $op = preg_replace('/[^A-Za-z]/', '', substr($soapAction, strrpos($soapAction, '/') + 1));
            @file_put_contents($debugDir . '/debug_' . $op . '_request.xml', $xml);
            @file_put_contents($debugDir . '/debug_' . $op . '_response.xml', $body === false ? '' : (strlen($body) > 200000 ? substr($body, 0, 200000) . '...[recortado]' : $body));
            @file_put_contents($debugDir . '/debug_' . $op . '_http.txt', 'BUILD: ' . self::BUILD . PHP_EOL . 'URL: ' . $url . PHP_EOL . 'HTTP CODE: ' . $code . PHP_EOL . 'CURL ERROR: ' . $errno . ' ' . $err . PHP_EOL . 'SERVER UTC: ' . gmdate('Y-m-d\TH:i:s\Z'));
        }

        curl_close($ch);
        if ($body === false) {
            $msg = 'Error de comunicación con SAT (' . $errno . '): ' . $err;
            if (in_array($errno, self::$curlTransitorios, true)) {
                throw new SatNetworkException($msg);
            }
            throw new Exception($msg);
        }
        if ($code === 503 || $code === 502 || $code === 504) {
            throw new SatNetworkException('El SAT no está disponible (HTTP ' . $code . ').');
        }
        if ($code < 200 || $code >= 300) {
            // Los servicios WCF del SAT envian SOAP Fault tambien con HTTP 500.
            // Extraemos faultcode/faultstring para facilitar el diagnostico.
            try {
                $fault = self::firstElementByLocalName($body, 'Fault');
                if ($fault) {
                    $faultCode = self::firstChildTextByLocalName($fault, 'faultcode');
                    $faultString = self::firstChildTextByLocalName($fault, 'faultstring');
                    $detail = trim(($faultCode !== '' ? $faultCode . ': ' : '') . $faultString);
                    if ($detail !== '') {
                        throw new Exception('SAT respondio HTTP ' . $code . ': ' . $detail);
                    }
                }
            } catch (Exception $faultException) {
                if (strpos($faultException->getMessage(), 'SAT respondio HTTP ') === 0) {
                    throw $faultException;
                }
            }
            $respuestaCorta = self::shortText($body);
            if ($respuestaCorta === '') {
                $respuestaCorta = 'Respuesta vacia. Activa debug_soap en config/sat_config.php y revisa la carpeta logs/.';
            }
            throw new Exception('SAT respondio HTTP ' . $code . ': ' . $respuestaCorta);
        }
        return array('http_code' => $code, 'body' => $body);
    }

    private static function throwSoapFault($xml)
    {
        $fault = self::firstElementByLocalName($xml, 'Fault');
        if (!$fault) return;
        $faultString = self::firstChildTextByLocalName($fault, 'faultstring');
        if ($faultString === '') $faultString = trim($fault->textContent);
        throw new Exception('SOAP Fault SAT: ' . $faultString);
    }

    private static function firstElementByLocalNames($xml, $localNames)
    {
        foreach ($localNames as $localName) {
            $node = self::firstElementByLocalName($xml, $localName);
            if ($node) return $node;
        }
        return null;
    }

    private static function firstElementByLocalName($xml, $localName)
    {
        $dom = self::loadXml($xml);
        $xp = new DOMXPath($dom);
        $nodes = $xp->query('//*[local-name()="' . $localName . '"]');
        if ($nodes && $nodes->length > 0) return $nodes->item(0);
        return null;
    }

    private static function firstChildTextByLocalName($node, $localName)
    {
        $xp = new DOMXPath($node->ownerDocument);
        $nodes = $xp->query('.//*[local-name()="' . $localName . '"]', $node);
        return ($nodes && $nodes->length) ? trim($nodes->item(0)->textContent) : '';
    }

    private static function xmlAttr($node, $name)
    {
        return ($node && $node->hasAttribute($name)) ? trim($node->getAttribute($name)) : '';
    }

    private static function loadXml($xml)
    {
        $dom = new DOMDocument();
        $old = libxml_use_internal_errors(true);
        if (!$dom->loadXML($xml)) {
            $errs = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($old);
            throw new Exception('Respuesta XML inválida del SAT: ' . (isset($errs[0]) ? trim($errs[0]->message) : 'Error XML'));
        }
        libxml_use_internal_errors($old);
        return $dom;
    }

    private static function pemWrap($data, $label)
    {
        if (strpos($data, '-----BEGIN ') !== false) return $data;
        return "-----BEGIN " . $label . "-----\n" . chunk_split(base64_encode($data), 64, "\n") . "-----END " . $label . "-----\n";
    }

    private static function issuerString($parsed)
    {
        if (!isset($parsed['issuer']) || !is_array($parsed['issuer'])) return '';
        $map = array(
            'CN' => 'CN', 'O' => 'O', 'OU' => 'OU',
            'emailAddress' => 'E', 'E' => 'E',
            'streetAddress' => 'STREET', 'postalCode' => 'PostalCode',
            'C' => 'C', 'ST' => 'S', 'L' => 'L',
            'serialNumber' => 'SERIALNUMBER',
            '1.2.840.113549.1.9.2' => 'OID.1.2.840.113549.1.9.2',
            '2.5.4.45' => 'OID.2.5.4.45'
        );
        $parts = array();
        // El SAT suele mostrar el DN en orden inverso al arreglo de openssl_x509_parse.
        foreach ($parsed['issuer'] as $k => $v) {
            $name = isset($map[$k]) ? $map[$k] : $k;
            $vals = is_array($v) ? $v : array($v);
            foreach ($vals as $vv) {
                $vv = (string) $vv;
                if (strpos($vv, ',') !== false || strpos($vv, '"') !== false) {
                    $vv = '"' . str_replace('"', '\\"', $vv) . '"';
                }
                $parts[] = $name . '=' . $vv;
            }
        }
        return implode(', ', array_reverse($parts));
    }

    private static function serialDecimal($parsed)
    {
        if (isset($parsed['serialNumber']) && ctype_digit((string)$parsed['serialNumber'])) {
            return (string)$parsed['serialNumber'];
        }
        if (isset($parsed['serialNumberHex'])) {
            return self::hexToDec((string)$parsed['serialNumberHex']);
        }
        if (isset($parsed['serialNumber'])) return (string)$parsed['serialNumber'];
        return '';
    }

    private static function hexToDec($hex)
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', $hex);
        $dec = '0';
        for ($i = 0, $len = strlen($hex); $i < $len; $i++) {
            $n = hexdec($hex[$i]);
            $dec = self::decMulAdd($dec, 16, $n);
        }
        return $dec;
    }

    private static function decMulAdd($number, $mul, $add)
    {
        $carry = $add;
        $out = '';
        for ($i = strlen($number) - 1; $i >= 0; $i--) {
            $v = ((int)$number[$i]) * $mul + $carry;
            $out = ($v % 10) . $out;
            $carry = (int) floor($v / 10);
        }
        while ($carry > 0) {
            $out = ($carry % 10) . $out;
            $carry = (int) floor($carry / 10);
        }
        return ltrim($out, '0') === '' ? '0' : ltrim($out, '0');
    }

    private static function extractRfcFromCertificate($parsed)
    {
        $candidates = array();
        if (isset($parsed['subject']) && is_array($parsed['subject'])) {
            foreach ($parsed['subject'] as $v) {
                if (is_array($v)) foreach ($v as $x) $candidates[] = $x;
                else $candidates[] = $v;
            }
        }
        foreach ($candidates as $text) {
            if (preg_match('/\b([A-Z&Ñ]{3,4}\d{6}[A-Z0-9]{3})\b/i', (string)$text, $m)) {
                return strtoupper($m[1]);
            }
        }
        return '';
    }

    private static function resolvePath($path)
    {
        if ($path === '') return '';
        if ($path[0] === '/' || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) return $path;
        // Rutas relativas guardadas por la versión anterior: relativas a la raíz del módulo.
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }

    private static function dateStart($date)
    {
        if (strpos($date, 'T') !== false) return $date;
        return $date . 'T00:00:00';
    }

    private static function dateEnd($date)
    {
        if (strpos($date, 'T') !== false) return $date;
        return $date . 'T23:59:59';
    }

    /** Información del certificado sin autenticar (para validar al cargarlo). */
    public static function leerCertificado($cerPath)
    {
        $cerDer = file_get_contents($cerPath);
        if ($cerDer === false) throw new Exception('No se pudo leer el archivo CER.');
        $cert = @openssl_x509_read(self::pemWrap($cerDer, 'CERTIFICATE'));
        if (!$cert) throw new Exception('El archivo .cer no es un certificado válido.');
        $parsed = openssl_x509_parse($cert);
        $serial = self::serialDecimal($parsed);
        // El número de certificado SAT (20 dígitos) viene codificado en el serial hexadecimal.
        $noCert = '';
        if (isset($parsed['serialNumberHex'])) {
            $hex = (string)$parsed['serialNumberHex'];
            if (strlen($hex) % 2 === 1) $hex = '0' . $hex;
            $bin = @hex2bin($hex);
            if ($bin !== false && ctype_digit($bin)) $noCert = $bin;
        }
        $esFiel = true;
        $ou = isset($parsed['subject']['OU']) ? (string)(is_array($parsed['subject']['OU']) ? implode(' ', $parsed['subject']['OU']) : $parsed['subject']['OU']) : '';
        // Los CSD (sellos) traen OU con el nombre de la sucursal; la e.firma no.
        if ($ou !== '') $esFiel = false;
        return array(
            'rfc' => self::extractRfcFromCertificate($parsed),
            'no_certificado' => $noCert !== '' ? $noCert : $serial,
            'valid_from' => isset($parsed['validFrom_time_t']) ? date('Y-m-d H:i:s', $parsed['validFrom_time_t']) : null,
            'valid_to' => isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null,
            'posible_csd' => !$esFiel
        );
    }

    /** Valida que la contraseña abra la llave privada. */
    public static function validarLlave($keyPath, $password)
    {
        $keyDer = file_get_contents($keyPath);
        $pk = @openssl_pkey_get_private(self::pemWrap($keyDer, 'ENCRYPTED PRIVATE KEY'), $password);
        if (!$pk) $pk = @openssl_pkey_get_private($keyDer, $password);
        if (!$pk) throw new Exception('La contraseña no abre la llave privada (.key). ' . self::opensslErrors());
        return true;
    }

    private static function estadoSolicitudLabel($n)
    {
        $map = array('0'=>'ERROR_SAT','1'=>'ACEPTADA','2'=>'EN_PROCESO','3'=>'TERMINADA','4'=>'ERROR','5'=>'RECHAZADA','6'=>'VENCIDA');
        return isset($map[(string)$n]) ? $map[(string)$n] : ('ESTADO_' . (string)$n);
    }

    private static function utcIso($timestamp)
    {
        $sec = (int) floor($timestamp);
        $ms = (int) floor(($timestamp - $sec) * 1000);
        return gmdate('Y-m-d\TH:i:s', $sec) . '.' . str_pad((string)$ms, 3, '0', STR_PAD_LEFT) . 'Z';
    }

    private static function uuidV4()
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private static function opensslErrors()
    {
        $a = array();
        while ($e = openssl_error_string()) $a[] = $e;
        return implode(' | ', $a);
    }

    private static function shortText($text)
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags((string)$text)));
        return substr($text, 0, 1200);
    }
}

/** Falla de red transitoria (se reintenta automáticamente). */
class SatNetworkException extends Exception
{
}
