# Plugin Xertify para Moodle

Esta guia es para instalar y configurar el plugin sin tocar codigo.

## 1. Instalar el plugin

1. Entrar a Moodle como administrador.
2. Ir a `Administracion del sitio > Plugins > Instalar plugins`.
3. Subir el archivo `local_xertify_1.1.0.zip`.
4. Confirmar la instalacion.
5. Moodle mostrara una pantalla de verificacion. Continuar hasta finalizar.

## 2. Configuracion global

Ir a:

`Administracion del sitio > Plugins > Plugins locales > Xertify`

Configurar:

- `Xertify API URL`: `https://api.xertify.co/v4createRequest`
- `Xertify API token`: token entregado por Xertify
- `Default document type`: `1`
- `Solo simular - no emitir certificados`: desactivado

Recomendacion: mantener `Solo simular - no emitir certificados` desactivado para cualquier prueba donde se quiera validar la emision real en Xertify. Si se activa, Moodle no llama la API de Xertify y no se emite certificado.

## 3. Configuracion por curso

Entrar al curso en Moodle y abrir:

`Mas > Certificados Xertify`

Configurar:

- `Enable automatic issuing`: activado
- `Xertify template ID`: ID de la plantilla en Xertify
- `Xertify group ID`: ID del grupo/programa en Xertify
- `Document type`: `1`
- `Course API token override`: dejar vacio si ya se configuro token global
- `Issue rule`: `Course completed`
- `Notificaciones Xertify`: activar email, SMS o WhatsApp segun lo que quiera enviar Xertify

Guardar cambios.

## 4. Documento del estudiante

El plugin envia a Xertify el campo `idnum` como numero de identificacion.

Moodle debe tener ese dato cargado en alguno de estos campos:

1. Campo nativo `Numero ID` del usuario (`idnumber`).
2. Campo personalizado con nombre corto `documento`.
3. Campo personalizado con nombre corto `document`.

Si no existe documento, el plugin usara el ID interno de Moodle solo como fallback tecnico. Para produccion, cargar siempre la identificacion real.

## 5. Flujo de emision

1. El estudiante completa el curso.
2. Moodle dispara el evento de curso completado.
3. El plugin envia la solicitud a Xertify.
4. Xertify emite el certificado segun plantilla y grupo configurados.

## 6. Payload enviado a Xertify

El plugin envia `v4createRequest` sin firmantes:

```json
{
  "runNow": true,
  "participants": [
    {
      "automatic": true,
      "profile": "p",
      "groupId": "ID_GRUPO",
      "templateId": "ID_TEMPLATE",
      "firstname": "Nombre",
      "lastname": "Apellido",
      "idtype": "1",
      "idnum": "Numero de identificacion",
      "email": "correo@estudiante.com",
      "phone": "",
      "notifications": {
        "sms": 0,
        "email": 1,
        "whatsapp": 0
      },
      "dynamicData": {
        "curso": "Nombre del curso",
        "dia": "16",
        "mes": "junio",
        "anio": "2026"
      }
    }
  ]
}
```

Los campos personalizados del perfil de Moodle tambien se envian dentro de `dynamicData`.
El nombre corto del campo debe coincidir con la variable de la plantilla Xertify. Por ejemplo, para `${ciudad}` el nombre corto en Moodle debe ser `ciudad`, no `ciudad2`.

## 7. Soporte

Si el certificado no se emite, validar:

- El curso tiene `Certificados Xertify` activado.
- El estudiante tiene numero de identificacion.
- El `templateId` pertenece a la cuenta Xertify.
- El `groupId` pertenece a la cuenta Xertify.
- El token API es valido.
- `Solo simular - no emitir certificados` esta desactivado.
- Las notificaciones del curso estan activadas solo si el cliente quiere que Xertify envie mensajes.

## Actualización 1.0.3 y actividad de certificado

No desinstalar `local_xertify` 1.0.3: hacer backup según la política del
administrador, actualizar primero a 1.1.0 y completar el upgrade de Moodle.
Verificar la configuración existente. Después se puede instalar el paquete
independiente `mod_xertifycertificate_1.0.0.zip` y completar su instalación.

En cada curso, activar edición y elegir **Añadir una actividad o recurso >
Certificado Xertify**; opcionalmente usar la sección **Certificación**. Las
actividades no se crean automáticamente. Token, Template y Group siguen
configurándose exclusivamente en el plugin local.

Aplicar restricciones estándar si se desea. Evitar que la finalización del curso
dependa de ver el certificado cuando su emisión depende de finalizar ese curso.
Al restaurar o duplicar un curso, no se copian certificados ni el token específico;
una integración nueva queda deshabilitada y debe revisarse antes de activarla.
Si una restauración parcial omite un requisito, revisar la restricción que Moodle
puede dejar sin referencia válida. Las configuraciones de destino ya existentes
se conservan conforme a la política de restore.

La primera release cubre la experiencia web responsive; la integración nativa
específica con Moodle App queda fuera de alcance.
