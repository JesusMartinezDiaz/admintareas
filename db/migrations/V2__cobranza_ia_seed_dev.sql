-- Datos de ejemplo para desarrollo local. NO ejecutar en producción tal
-- cual: el codigo de plantilla debe coincidir con un HSM real ya
-- aprobado en WhatsApp Business Manager.

INSERT INTO cob_plantilla_mensaje (codigo, nombre_visible, categoria, idioma, cuerpo_texto, activo)
VALUES (
    'recordatorio_saldo_biochem_v1',
    'Recordatorio de saldo (Biochem)',
    'RECORDATORIO',
    'es_MX',
    'Hola {{1}}, te escribimos de Biochem Systems. Tu saldo actual es de ${{2}} MXN. ¿Nos ayudas a confirmarlo o coordinar tu pago?',
    1
);

INSERT INTO cob_regla_pronto_pago (id_compania, nombre, dias_max_desde_factura, pct_descuento,
                                    monto_max_autorizado_automatico, aplica_a, activo, vigencia_inicio)
VALUES (
    19,
    'Pronto pago general Biochem (5 días, 3%)',
    5,
    3.00,
    1500.00,
    'TODOS',
    1,
    CURDATE()
);
