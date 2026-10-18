<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Spanish language strings for Xertify certificates.
 *
 * @package    local_xertify
 * @copyright  2026 Xertify
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Xertify';
$string['privacy:export:issuance'] = 'Emisión de certificado';
$string['privacy:export:redacted'] = '[Oculto]';
$string['privacy:export:responsebody'] = 'Solo datos estructurados guardados de esta emisión. Se omiten credenciales, documentos embebidos, destinatarios no verificados, texto opaco y campos no admitidos. No se consultaron datos externos.';
$string['xertify:manage'] = 'Gestionar configuracion de certificados Xertify';
$string['privacy:metadata:xertify_api'] = 'Los datos del destinatario se envian a la API configurada de Xertify para emitir certificados digitales.';
$string['privacy:metadata:xertify_api:firstname'] = 'El nombre del destinatario.';
$string['privacy:metadata:xertify_api:lastname'] = 'El apellido del destinatario.';
$string['privacy:metadata:xertify_api:email'] = 'El correo electronico del destinatario.';
$string['privacy:metadata:xertify_api:idtype'] = 'El tipo de documento del destinatario.';
$string['privacy:metadata:xertify_api:idnum'] = 'El numero de documento del destinatario.';
$string['privacy:metadata:xertify_api:phone'] = 'El telefono del destinatario, cuando esta disponible.';
$string['privacy:metadata:xertify_api:course'] = 'Datos del curso usados como campos dinamicos del certificado.';
$string['privacy:metadata:xertify_api:profilefields'] = 'Campos personalizados del perfil usados como campos dinamicos del certificado.';
$string['privacy:metadata:local_xertify_issue'] = 'Guarda intentos de emision de certificados Xertify para auditoria y diagnostico.';
$string['privacy:metadata:local_xertify_issue:courseid'] = 'El curso de Moodle relacionado con el intento de emision.';
$string['privacy:metadata:local_xertify_issue:id'] = 'El identificador local del registro de emisión asociado al usuario.';
$string['privacy:metadata:local_xertify_issue:userid'] = 'El usuario de Moodle relacionado con el intento de emision.';
$string['privacy:metadata:local_xertify_issue:status'] = 'El estado del intento de emision.';
$string['privacy:metadata:local_xertify_issue:requesthash'] = 'Un hash del payload enviado a la API de Xertify.';
$string['privacy:metadata:local_xertify_issue:responsecode'] = 'El codigo HTTP retornado por la API de Xertify.';
$string['privacy:metadata:local_xertify_issue:responsebody'] = 'El cuerpo de respuesta retornado por la API de Xertify o el payload registrado en modo prueba.';
$string['privacy:metadata:local_xertify_issue:timecreated'] = 'La fecha de creacion del intento de emision.';
$string['privacy:metadata:local_xertify_issue:timemodified'] = 'La fecha de ultima actualizacion del intento de emision.';
$string['apiurl'] = 'URL de API Xertify';
$string['apiurl_desc'] = 'Endpoint que recibe solicitudes de certificado. Usa /v4createRequest para la API actual de Xertify.';
$string['apitoken'] = 'Token API Xertify';
$string['apitoken_desc'] = 'Bearer token de Xertify. El token por curso puede sobrescribir este valor.';
$string['defaultidtype'] = 'Tipo de documento por defecto';
$string['defaultidtype_desc'] = 'Tipo de documento enviado a Xertify cuando el curso no lo sobrescribe.';
$string['dryrun'] = 'Solo simular - no emitir certificados';
$string['dryrun_desc'] = 'Mantener desactivado para pruebas reales y produccion. Si esta activo, Moodle solo registra la solicitud localmente: NO llama la API de Xertify y NO emite certificados.';
$string['courseconfig'] = 'Certificados Xertify';
$string['enabled'] = 'Activar emision automatica';
$string['templateid'] = 'Template ID Xertify';
$string['groupid'] = 'Group ID Xertify';
$string['idtype'] = 'Tipo de documento';
$string['courseapitoken'] = 'Token API especifico del curso';
$string['emitrule'] = 'Regla de emision';
$string['emitrule_course_completed'] = 'Curso completado';
$string['coursedynamicdata'] = 'Datos dinamicos del curso';
$string['coursedynamicdata_help'] = 'Ingresa un campo dinamico por linea usando clave=valor. La clave debe coincidir con la variable de la plantilla Xertify sin ${}. Ejemplo: ciudad=Bogota';
$string['invaliddynamicdata'] = 'Los datos dinamicos del curso deben usar un par clave=valor por linea. Las claves deben iniciar con una letra y contener solo letras, numeros y guiones bajos.';
$string['notifications'] = 'Notificaciones Xertify';
$string['notification_email'] = 'Enviar notificacion por email';
$string['notification_sms'] = 'Enviar notificacion por SMS';
$string['notification_whatsapp'] = 'Enviar notificacion por WhatsApp';
$string['savechanges'] = 'Guardar cambios';
$string['settingssaved'] = 'Configuracion Xertify guardada.';
$string['missingconfig'] = 'La configuracion Xertify del curso esta incompleta.';
$string['resetattempts'] = 'Limpiar intentos de emision';
$string['resetattemptsconfirm'] = 'Esto elimina el historial local de intentos de emision Xertify para este curso. Usalo solo cuando necesites repetir pruebas con los mismos estudiantes.';
$string['resetattemptsdone'] = 'Los intentos de emision Xertify fueron limpiados para este curso.';
$string['resetattempt'] = 'Limpiar intento';
$string['resetattemptdone'] = 'El intento de emision Xertify seleccionado fue limpiado.';
$string['recentattempts'] = 'Intentos de emision recientes';
$string['student'] = 'Estudiante';
$string['status'] = 'Estado';
$string['responsecode'] = 'Codigo de respuesta';
$string['timemodified'] = 'Ultima modificacion';

$string['mycertificate'] = 'Mi certificado Xertify';
$string['certificateavailable'] = 'Tu certificado está disponible.';
$string['certificatenotavailable'] = 'Este certificado Xertify no está disponible.';
$string['certificatecode'] = 'Código del certificado';
$string['viewcertificate'] = 'Ver certificado';
$string['downloadpdf'] = 'Descargar PDF';
$string['verifyxertify'] = 'Verificar en Xertify';
$string['pdfserviceerror'] = 'No se pudo obtener el PDF del certificado desde Xertify. Intenta nuevamente más tarde.';
$string['privacy:metadata:local_xertify_issue:generationid'] = 'Identificador de la generación de Xertify asociada a la emisión.';
$string['privacy:metadata:local_xertify_issue:certificatecode'] = 'Código individual del certificado Xertify.';
$string['privacy:metadata:local_xertify_issue:certificateurl'] = 'URL pública de verificación del certificado Xertify.';
