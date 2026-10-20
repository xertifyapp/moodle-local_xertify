# Guia de implementacion - Plugin Xertify Certificates para Moodle

Documento para Atencion al Cliente, Customer Success y equipos de implementacion.

## Objetivo

El plugin Xertify Certificates permite que Moodle solicite automaticamente la emision de certificados digitales en Xertify cuando un estudiante completa un curso.

El cliente no debe modificar bases de datos ni desarrollar codigo. La integracion se realiza instalando el plugin en Moodle y configurando los IDs entregados por Xertify.

## Compatibilidad

- Moodle 4.1–5.2 (combinaciones PHP compatibles validadas).
- PHP compatible con la version de Moodle instalada.
- El servidor Moodle debe tener salida HTTPS hacia:

```text
https://api.xertify.co/v4createRequest
```

## Informacion que debe preparar Xertify

Antes de instalar o configurar el plugin, Xertify debe dejar lista esta informacion:

1. Cuenta u organizacion del cliente en Xertify.
2. Token API de la cuenta.
3. Plantilla de certificado creada y probada.
4. `templateId` de la plantilla.
5. Grupo o programa creado en Xertify.
6. `groupId` del grupo o programa.
7. Variables dinamicas usadas por la plantilla.
8. Definicion de notificaciones:
   - Email.
   - SMS.
   - WhatsApp.
   
Ejemplo de datos que Xertify entrega:

```text
API URL: https://api.xertify.co/v4createRequest
API token: entregar por canal seguro
Template ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
Group ID: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
Document type: 1
Notifications: email=1, sms=0, whatsapp=0
```

## Informacion que debe preparar el cliente

El cliente debe confirmar o preparar en Moodle:

1. Acceso administrador a Moodle.
2. Version de Moodle.
3. Permiso para instalar plugins.
4. Curso o cursos donde se emitiran certificados.
5. Finalizacion de curso habilitada en Moodle.
6. Reglas de finalizacion configuradas en cada curso.
7. Estudiantes inscritos en los cursos.
8. Datos basicos de cada estudiante:
   - Nombre.
   - Apellido.
   - Email.
   - Numero de identificacion.
9. Campos dinamicos requeridos por la plantilla, si aplican.

## Datos del estudiante requeridos

El plugin envia a Xertify estos datos del estudiante:

```text
firstname
lastname
email
idtype
idnum
phone
```

El numero de identificacion (`idnum`) se toma en este orden:

1. Campo nativo de Moodle `idnumber`.
2. Campo personalizado `document`.
3. Campo personalizado `documento`.
4. Campo personalizado `idnum`.
5. Campo personalizado `numdoc`.

Recomendacion: para produccion, el cliente debe cargar el documento real del estudiante en `idnumber` o en un campo personalizado equivalente.

## Variables dinamicas

Las variables dinamicas son datos que la plantilla de Xertify usa para pintar textos en el certificado.

Ejemplo de variables en plantilla:

```text
${curso}
${evento}
${intensidad}
${ciudad}
${fechaevento}
${mesexpide}
```

El nombre de la variable debe coincidir exactamente con el dato enviado desde Moodle.

## Tipos de variables dinamicas

### Variables del curso

Son iguales para todos los estudiantes del curso. Se configuran dentro del curso en Moodle.

Ejemplos:

```text
evento=Inteligencia de negocios
intensidad=40 horas
ciudad=Bogota
tipoevento=Diplomado
conector=asistio
mesexpide=julio
fechaevento=22/07/2026
```

Estas variables son recomendadas para datos que dependen del curso o evento, no del estudiante.

### Variables del estudiante

Son propias de cada estudiante. Se toman de campos nativos de Moodle o campos personalizados del perfil.

Ejemplos:

```text
firstname
lastname
email
idnumber
profile_birthdate
profile_city
```

El plugin incluye campos personalizados de Moodle en `dynamicData` usando el nombre corto del campo. Tambien puede incluir la version con prefijo `profile_` por compatibilidad.

## Formato enviado a Xertify

El plugin envia la solicitud al endpoint `v4createRequest` sin firmantes.

La estructura esperada es:

```json
{
  "runNow": true,
  "participants": [
    {
      "automatic": true,
      "profile": "p",
      "groupId": "ID_DEL_GRUPO",
      "templateId": "ID_DE_LA_PLANTILLA",
      "firstname": "Nombre",
      "lastname": "Apellido",
      "idtype": "1",
      "idnum": "Numero de identificacion",
      "email": "correo@cliente.com",
      "phone": "",
      "notifications": {
        "sms": 0,
        "email": 1,
        "whatsapp": 0
      },
      "dynamicData": {
        "curso": "Nombre del curso",
        "dia": "28",
        "mes": "julio",
        "anio": "2026",
        "evento": "Inteligencia de negocios",
        "intensidad": "40 horas",
        "ciudad": "Bogota"
      }
    }
  ]
}
```

Nota importante: `dynamicData` debe llegar como objeto JSON plano, no como una lista de objetos.

## Paso a paso de instalacion

### 1. Instalar el plugin

1. Entrar a Moodle como administrador.
2. Ir a:

```text
Administracion del sitio > Plugins > Instalar plugins
```

3. Subir el archivo ZIP del plugin.
4. Confirmar la instalacion.
5. Moodle mostrara una pantalla de validacion.
6. Continuar hasta finalizar.
7. Si Moodle solicita actualizar base de datos, aceptar la actualizacion.

### 2. Configurar el plugin globalmente

Ir a:

```text
Administracion del sitio > Plugins > Plugins locales > Xertify
```

Configurar:

```text
Xertify API URL: https://api.xertify.co/v4createRequest
Xertify API token: token entregado por Xertify
Default document type: 1
Solo simular - no emitir certificados: desactivado
```

Recomendacion:

- Mantener `Solo simular - no emitir certificados` desactivado para pruebas reales y produccion.
- Activarlo solo si Xertify necesita revisar el payload localmente sin llamar la API ni emitir certificados.

### 3. Configurar el curso

Entrar al curso y abrir:

```text
Mas > Certificados Xertify
```

Configurar:

```text
Activar emision automatica: si
Template ID Xertify: ID de plantilla entregado por Xertify
Group ID Xertify: ID de grupo entregado por Xertify
Tipo de documento: 1
Token API especifico del curso: dejar vacio si se usa el token global
Regla de emision: Course completed
Email/SMS/WhatsApp: segun definicion del cliente
Datos dinamicos del curso: clave=valor, una variable por linea
```

Ejemplo de datos dinamicos del curso:

```text
evento=Inteligencia de negocios
intensidad=40 horas
ciudad=Bogota
tipoevento=Diplomado
conector=asistio
mesexpide=julio
fechaevento=22/07/2026
```

Guardar cambios.

### 4. Configurar finalizacion del curso

El plugin emite cuando Moodle dispara el evento de curso completado.

Validar en Moodle:

1. La finalizacion del curso esta activada.
2. El curso tiene criterios de finalizacion.
3. La actividad requerida se puede marcar como completada.
4. El estudiante esta inscrito en el curso.

### 5. Ejecutar prueba

1. Crear o seleccionar un estudiante de prueba.
2. Confirmar que tiene nombre, apellido, email y numero de identificacion.
3. Inscribirlo en el curso.
4. Completar el curso como estudiante o marcar la actividad segun las reglas del cliente.
5. Esperar que Moodle procese la finalizacion.
6. Revisar en Xertify que haya llegado la solicitud API.
7. Confirmar que el certificado se emitio.
8. Abrir el certificado en Wallet/Verificacion.
9. Validar que las variables se pintaron correctamente.

### 6. Repetir una prueba con el mismo estudiante

El plugin evita llamadas duplicadas a Xertify para el mismo estudiante y curso. Si se necesita repetir una prueba con el mismo estudiante:

1. Entrar al curso.
2. Ir a:

```text
Mas > Certificados Xertify
```

3. Buscar al estudiante en la tabla de intentos recientes.
4. Usar el boton del estudiante:

```text
Limpiar intento
```

5. Reiniciar la finalizacion del estudiante en Moodle, si aplica.
6. Completar nuevamente el curso.

Usar `Limpiar intentos de emision` solo cuando se quiera limpiar todo el historial local de intentos del curso.

Esto evita que Atencion al Cliente tenga que borrar registros manualmente desde la base de datos.

## Validacion en Xertify

Despues de la prueba, validar:

1. La solicitud API aparece en Xertify.
2. El participante quedo en el grupo correcto.
3. La plantilla usada es la correcta.
4. El certificado fue emitido.
5. El documento del estudiante se ve correctamente.
6. Las variables dinamicas coinciden con la plantilla.
7. Las notificaciones se enviaron o quedaron desactivadas segun lo configurado.

## Checklist para salida a produccion

Antes de dejar activo al cliente:

- [ ] Cuenta Xertify creada.
- [ ] Token API generado.
- [ ] Plantilla aprobada por el cliente.
- [ ] Grupo/programa creado.
- [ ] `templateId` confirmado.
- [ ] `groupId` confirmado.
- [ ] Variables de plantilla documentadas.
- [ ] Plugin instalado en Moodle.
- [ ] API URL configurada.
- [ ] Token API configurado.
- [ ] Curso configurado con template y grupo.
- [ ] Finalizacion del curso configurada.
- [ ] Estudiante de prueba inscrito.
- [ ] Prueba de emision realizada.
- [ ] Certificado validado en Xertify Wallet.
- [ ] `Solo simular - no emitir certificados` desactivado para produccion.

## Problemas frecuentes

### No se emite certificado

Validar:

- El plugin esta activado en el curso.
- El curso realmente quedo completado en Moodle.
- El cron de Moodle esta corriendo.
- El token API es correcto.
- El `templateId` es correcto.
- El `groupId` es correcto.
- `Solo simular - no emitir certificados` no esta activado para produccion.

### El certificado se emite pero faltan variables

Validar:

- La variable existe en la plantilla.
- El nombre enviado desde Moodle coincide exactamente.
- No hay errores de escritura, mayusculas o espacios.
- Si es dato de curso, esta configurado en `Datos dinamicos del curso`.
- Si es dato de estudiante, existe en el perfil del estudiante.

### El documento del estudiante sale incorrecto

Validar:

- El campo `idnumber` del usuario tiene el documento real.
- Si usan campo personalizado, el nombre corto es `document`, `documento`, `idnum` o `numdoc`.
- No se esta usando el ID interno de Moodle como fallback.

### No llegan correos

Validar:

- En el curso esta activada la notificacion por email.
- El email del estudiante es correcto.
- Xertify tiene habilitada la notificacion correspondiente.
- El correo no quedo en spam.

## Responsabilidades

### Xertify

- Crear cuenta.
- Crear plantilla.
- Crear grupo/programa.
- Generar token API.
- Entregar `templateId`, `groupId` y variables dinamicas.
- Acompanhar prueba de emision.
- Validar solicitud y certificado en Xertify.

### Cliente

- Dar acceso administrador a Moodle.
- Instalar el plugin.
- Configurar cursos y finalizacion.
- Cargar datos correctos de estudiantes.
- Confirmar variables que deben aparecer en la plantilla.
- Ejecutar prueba funcional con un estudiante.

## Informacion minima para iniciar una implementacion

Para abrir una implementacion, Atencion al Cliente debe recopilar:

```text
Nombre del cliente:
URL de Moodle:
Version de Moodle:
Responsable tecnico del cliente:
Curso piloto:
Plantilla Xertify:
Grupo/programa Xertify:
Notificaciones requeridas:
Variables dinamicas de la plantilla:
Campo donde el cliente guarda el documento del estudiante:
Fecha estimada de prueba:
```

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
