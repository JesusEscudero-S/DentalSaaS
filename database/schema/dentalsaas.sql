CREATE TABLE `organizations` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `commercial_name` varchar(200) NOT NULL,
  `legal_name` varchar(300),
  `rfc` varchar(13) COMMENT 'CHECK formato RFC',
  `phone` varchar(30),
  `email` varchar(254),
  `address_line` varchar(300),
  `address_postal_code` varchar(5),
  `logo_storage_key` text COMMENT 'clave privada de almacenamiento',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `timezone` varchar(60) NOT NULL DEFAULT 'America/Mexico_City',
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `organization_fiscal_settings` (
  `organization_id` CHAR(36) PRIMARY KEY,
  `tax_regime_code` varchar(3) COMMENT 'catálogo SAT c_RegimenFiscal',
  `fiscal_postal_code` varchar(5),
  `pac_provider` varchar(60),
  `default_series` varchar(10),
  `csd_certificate_secret_ref` text COMMENT 'referencia a gestor de secretos; NUNCA llave/contraseña',
  `csd_key_secret_ref` text,
  `pac_credentials_secret_ref` text,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `folio_sequences` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `document_type` varchar(20) NOT NULL COMMENT 'patient, prescription, quote, invoice, ...',
  `series` varchar(10) NOT NULL DEFAULT '',
  `prefix` varchar(10) NOT NULL COMMENT 'PAC-, RX-, PRE-, F-',
  `last_value` bigint NOT NULL DEFAULT 0
);

CREATE TABLE `branches` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(150) NOT NULL,
  `address_line` varchar(300),
  `phone` varchar(30),
  `manager_user_id` CHAR(36),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active',
  `is_main` boolean NOT NULL DEFAULT false,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `users` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()) COMMENT 'coincide con sub del proveedor de auth',
  `auth_provider_id` varchar(200) UNIQUE,
  `full_name` varchar(200) NOT NULL,
  `email` varchar(254) UNIQUE NOT NULL COMMENT 'citext',
  `avatar_storage_key` text,
  `is_active` boolean NOT NULL DEFAULT true,
  `last_login_at` DATETIME,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `roles` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `is_system_admin` boolean NOT NULL DEFAULT false COMMENT 'no editable ni desactivable (trigger)',
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `permissions` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `module` varchar(50) NOT NULL COMMENT 'expedientes, datos_clinicos, citas, pacientes, facturacion, ...',
  `action` varchar(50) NOT NULL COMMENT 'ver, crear, editar, desactivar, exportar, firmar_notas, emitir_recetas, cancelar_cfdi, registrar_pagos',
  `description` text
);

CREATE TABLE `role_permissions` (
  `organization_id` CHAR(36) NOT NULL,
  `role_id` CHAR(36) NOT NULL,
  `permission_id` CHAR(36) NOT NULL,
  `granted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`role_id`, `permission_id`)
);

CREATE TABLE `organization_members` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36) NOT NULL,
  `role_id` CHAR(36) COMMENT 'NULL = sin acceso por defecto',
  `is_active` boolean NOT NULL DEFAULT true,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `member_branches` (
  `organization_id` CHAR(36) NOT NULL,
  `member_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  PRIMARY KEY (`member_id`, `branch_id`)
);

CREATE TABLE `doctor_profiles` (
  `member_id` CHAR(36) PRIMARY KEY,
  `organization_id` CHAR(36) NOT NULL,
  `professional_license` varchar(30) COMMENT 'cédula profesional',
  `specialty` varchar(120),
  `signature_image_storage_key` text COMMENT 'imagen de firma: NO equivale a firma electrónica'
);

CREATE TABLE `user_invitations` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `email` varchar(254) NOT NULL,
  `role_id` CHAR(36),
  `invited_by_member_id` CHAR(36),
  `token_hash` text NOT NULL COMMENT 'solo hash; token en claro nunca se guarda',
  `expires_at` DATETIME NOT NULL,
  `accepted_at` DATETIME,
  `revoked_at` DATETIME,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `user_sessions` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `user_id` CHAR(36) NOT NULL,
  `organization_id` CHAR(36),
  `auth_session_id` varchar(200),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` DATETIME NOT NULL,
  `revoked_at` DATETIME,
  `revoked_reason` varchar(60) COMMENT 'role_changed, permissions_changed, deactivated, logout'
);

CREATE TABLE `role_change_history` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `change_type` varchar(40) NOT NULL COMMENT 'user_role_assigned, role_permissions_changed, role_deactivated, branch_assigned',
  `target_member_id` CHAR(36),
  `role_id` CHAR(36),
  `old_value` JSON,
  `new_value` JSON,
  `actor_user_id` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `audit_log` (
  `id` bigint PRIMARY KEY AUTO_INCREMENT,
  `organization_id` CHAR(36) NOT NULL,
  `actor_user_id` CHAR(36),
  `entity_type` varchar(60) NOT NULL,
  `entity_id` CHAR(36) NOT NULL,
  `action` varchar(20) NOT NULL COMMENT 'insert, update, delete, sign, cancel',
  `changed_fields` JSON COMMENT 'solo campos relevantes; sin secretos ni texto clínico completo',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `patients` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `folio` varchar(20) NOT NULL COMMENT 'PAC-0481',
  `full_name` varchar(250) NOT NULL,
  `birth_date` date,
  `sex` ENUM ('female', 'male', 'other', 'unspecified') NOT NULL DEFAULT 'unspecified',
  `phone` varchar(30),
  `email` varchar(254),
  `assigned_doctor_member_id` CHAR(36),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME COMMENT 'baja lógica',
  `anonymized_at` DATETIME COMMENT 'proceso de anonimización según política legal'
);

CREATE TABLE `patient_fiscal_profiles` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `rfc` varchar(13) NOT NULL,
  `legal_name` varchar(300) NOT NULL,
  `tax_regime_code` varchar(3) NOT NULL,
  `fiscal_postal_code` varchar(5) NOT NULL,
  `default_cfdi_use` varchar(4) COMMENT 'c_UsoCFDI, ej. D01',
  `is_default` boolean NOT NULL DEFAULT true
);

CREATE TABLE `patient_guardians` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `full_name` varchar(250) NOT NULL,
  `relationship` varchar(60),
  `phone` varchar(30),
  `email` varchar(254),
  `is_primary` boolean NOT NULL DEFAULT true
);

CREATE TABLE `tax_profiles` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `tax_code` varchar(3) NOT NULL DEFAULT '002' COMMENT 'c_Impuesto SAT (002=IVA)',
  `rate` DECIMAL(7,6) NOT NULL COMMENT 'configurable; 0.160000 es ilustrativo',
  `is_exempt` boolean NOT NULL DEFAULT false,
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `treatment_categories` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(100) NOT NULL
);

CREATE TABLE `treatments` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `category_id` CHAR(36),
  `name` varchar(200) NOT NULL,
  `duration_minutes` integer NOT NULL COMMENT 'CHECK > 0',
  `sat_product_key` varchar(8) COMMENT 'c_ClaveProdServ',
  `sat_unit_key` varchar(3) DEFAULT 'E48',
  `price` DECIMAL(14,4) NOT NULL COMMENT 'CHECK >= 0',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `tax_profile_id` CHAR(36),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `appointments` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `doctor_member_id` CHAR(36) NOT NULL,
  `treatment_id` CHAR(36),
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL COMMENT 'CHECK ends_at > starts_at',
  `status` ENUM ('pendiente', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio') NOT NULL DEFAULT 'pendiente',
  `notes` text,
  `created_by` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `appointment_status_history` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `appointment_id` CHAR(36) NOT NULL,
  `from_status` ENUM ('pendiente', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio'),
  `to_status` ENUM ('pendiente', 'confirmada', 'en_curso', 'completada', 'cancelada', 'no_asistio') NOT NULL,
  `old_starts_at` DATETIME,
  `new_starts_at` DATETIME COMMENT 'reprogramaciones',
  `reason` text COMMENT 'motivo de cancelación',
  `actor_user_id` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `medical_records` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `allergies` text,
  `medical_history` text,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `clinical_encounters` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `medical_record_id` CHAR(36) NOT NULL,
  `appointment_id` CHAR(36),
  `branch_id` CHAR(36) NOT NULL,
  `doctor_member_id` CHAR(36) NOT NULL,
  `occurred_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `patient_treatments` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `treatment_id` CHAR(36) NOT NULL,
  `quote_item_id` CHAR(36),
  `tooth_fdi` smallint,
  `status` ENUM ('planeado', 'en_progreso', 'realizado', 'cancelado') NOT NULL DEFAULT 'planeado',
  `started_at` DATETIME,
  `completed_at` DATETIME
);

CREATE TABLE `clinical_notes` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `encounter_id` CHAR(36) NOT NULL,
  `note_type` ENUM ('simple', 'soap') NOT NULL DEFAULT 'simple',
  `author_member_id` CHAR(36) NOT NULL,
  `responsible_doctor_member_id` CHAR(36) NOT NULL,
  `treatment_id` CHAR(36),
  `tooth_fdi` smallint,
  `appointment_id` CHAR(36),
  `subjective` text,
  `objective` text,
  `analysis` text,
  `plan` text,
  `prescription_text` text,
  `status` ENUM ('borrador', 'firmada') NOT NULL DEFAULT 'borrador',
  `signed_at` DATETIME,
  `signed_by_member_id` CHAR(36),
  `content_hash` char(64) COMMENT 'SHA-256 del contenido firmado',
  `signature_mechanism` varchar(40) COMMENT 'hash_interno | fiel | proveedor_firma; imagen != firma legal',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `clinical_note_addenda` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `note_id` CHAR(36) NOT NULL,
  `version_number` integer NOT NULL,
  `content` text NOT NULL,
  `reason` text NOT NULL,
  `author_member_id` CHAR(36) NOT NULL,
  `content_hash` char(64) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `tooth_findings` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `tooth_fdi` smallint NOT NULL COMMENT 'CHECK FDI: permanentes 11-18,21-28,31-38,41-48; temporales 51-55,61-65,71-75,81-85',
  `dentition` ENUM ('permanente', 'temporal') NOT NULL,
  `condition` ENUM ('sano', 'caries', 'endodoncia', 'restauracion', 'ausente') NOT NULL,
  `surfaces` JSON COMMENT 'extensión: M, D, O, V, L/P',
  `encounter_id` CHAR(36),
  `patient_treatment_id` CHAR(36),
  `author_member_id` CHAR(36) NOT NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `superseded_at` DATETIME COMMENT 'al cambiar un hallazgo se cierra el anterior; nunca se borra'
);

CREATE TABLE `clinical_files` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `file_type` ENUM ('radiografia', 'consentimiento', 'fotografia_intraoral', 'otro') NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `size_bytes` bigint NOT NULL COMMENT 'CHECK > 0',
  `storage_key` text NOT NULL COMMENT 'clave privada; acceso por URL firmada de corta vida',
  `sha256` char(64),
  `uploaded_by_member_id` CHAR(36) NOT NULL,
  `encounter_id` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME
);

CREATE TABLE `consent_acceptances` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `clinical_file_id` CHAR(36) COMMENT 'documento adjunto (no equivale a aceptación)',
  `accepted_at` DATETIME NOT NULL,
  `accepted_by_name` varchar(250) NOT NULL COMMENT 'paciente o tutor',
  `method` varchar(40) NOT NULL COMMENT 'firma_autografa_escaneada | firma_digital | verbal_registrada',
  `witness_member_id` CHAR(36),
  `content_hash` char(64)
);

CREATE TABLE `medications` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) COMMENT 'NULL = catálogo global',
  `name` varchar(200) NOT NULL,
  `active_ingredient` varchar(200),
  `strength` varchar(80),
  `presentation` varchar(120),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `prescriptions` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `folio` varchar(20) NOT NULL COMMENT 'RX-0148',
  `patient_id` CHAR(36) NOT NULL,
  `doctor_member_id` CHAR(36) NOT NULL,
  `issued_on` date NOT NULL,
  `diagnosis` text,
  `general_instructions` text,
  `status` ENUM ('borrador', 'firmada') NOT NULL DEFAULT 'borrador' COMMENT 'firmada = emitida; sin estado intermedio',
  `signed_at` DATETIME,
  `doctor_name_snapshot` varchar(200) COMMENT 'se fija al emitir',
  `doctor_license_snapshot` varchar(30),
  `content_hash` char(64),
  `pdf_storage_key` text,
  `repeated_from_id` CHAR(36) COMMENT 'repetir crea receta nueva',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `prescription_items` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `prescription_id` CHAR(36) NOT NULL,
  `line_number` smallint NOT NULL,
  `medication_id` CHAR(36),
  `medication_name` varchar(200) NOT NULL COMMENT 'snapshot',
  `strength` varchar(80),
  `presentation` varchar(120),
  `dose` varchar(120) NOT NULL,
  `route` varchar(60),
  `frequency` varchar(120) NOT NULL,
  `duration` varchar(120),
  `instructions` text
);

CREATE TABLE `prescription_deliveries` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `prescription_id` CHAR(36) NOT NULL,
  `channel` varchar(20) NOT NULL COMMENT 'impresion, email, whatsapp',
  `sent_at` DATETIME,
  `actor_user_id` CHAR(36),
  `outbox_event_id` CHAR(36)
);

CREATE TABLE `quotes` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `folio` varchar(20) NOT NULL COMMENT 'PRE-0118',
  `patient_id` CHAR(36) NOT NULL,
  `doctor_member_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `quote_date` date NOT NULL,
  `status` ENUM ('borrador', 'enviado', 'aprobado', 'rechazado') NOT NULL DEFAULT 'borrador',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `discount_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0 COMMENT 'CHECK total = subtotal - discount_total + tax_total; redondeo por concepto a 2 decimales (half-up)',
  `sent_at` DATETIME,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `quote_items` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `quote_id` CHAR(36) NOT NULL,
  `line_number` smallint NOT NULL,
  `treatment_id` CHAR(36),
  `description_snapshot` varchar(300) NOT NULL,
  `sat_product_key_snapshot` varchar(8),
  `tooth_fdi` smallint,
  `quantity` DECIMAL(14,4) NOT NULL COMMENT 'CHECK > 0',
  `unit_price` DECIMAL(14,4) NOT NULL,
  `discount_pct` DECIMAL(5,2) NOT NULL DEFAULT 0 COMMENT 'CHECK 0..100',
  `tax_rate_snapshot` DECIMAL(7,6) NOT NULL DEFAULT 0,
  `tax_is_exempt` boolean NOT NULL DEFAULT false,
  `line_subtotal` DECIMAL(14,2) NOT NULL,
  `line_discount` DECIMAL(14,2) NOT NULL,
  `line_tax` DECIMAL(14,2) NOT NULL,
  `line_total` DECIMAL(14,2) NOT NULL
);

CREATE TABLE `quote_status_history` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `quote_id` CHAR(36) NOT NULL,
  `from_status` ENUM ('borrador', 'enviado', 'aprobado', 'rechazado'),
  `to_status` ENUM ('borrador', 'enviado', 'aprobado', 'rechazado') NOT NULL,
  `actor_user_id` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `invoices` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `internal_folio` varchar(20) NOT NULL COMMENT 'F-0228',
  `series` varchar(10),
  `fiscal_folio` integer COMMENT 'folio de serie fiscal',
  `uuid_fiscal` CHAR(36) COMMENT 'UUID SAT; solo lo escribe la respuesta real del PAC',
  `cfdi_type` ENUM ('I', 'E', 'P') NOT NULL DEFAULT 'I',
  `patient_id` CHAR(36) NOT NULL,
  `doctor_member_id` CHAR(36),
  `branch_id` CHAR(36) NOT NULL,
  `quote_id` CHAR(36),
  `issued_at` DATETIME,
  `stamped_at` DATETIME,
  `issuer_snapshot` JSON NOT NULL COMMENT 'RFC, razón social, régimen, CP',
  `receiver_snapshot` JSON NOT NULL,
  `cfdi_use` varchar(4) NOT NULL,
  `cfdi_payment_method` ENUM ('PUE', 'PPD') NOT NULL COMMENT 'PUE / PPD',
  `cfdi_payment_form` varchar(2) NOT NULL COMMENT 'c_FormaPago (01,03,04,99...)',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `exchange_rate` DECIMAL(12,6) DEFAULT 1,
  `subtotal` DECIMAL(14,2) NOT NULL,
  `discount_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax_total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(14,2) NOT NULL,
  `fiscal_status` ENUM ('borrador', 'pendiente_timbrado', 'timbrada', 'cancelacion_solicitada', 'cancelada', 'error') NOT NULL DEFAULT 'borrador',
  `collection_status` ENUM ('pendiente', 'pago_parcial', 'pagada') NOT NULL DEFAULT 'pendiente' COMMENT 'derivado/validado desde payment_applications',
  `xml_storage_key` text,
  `pdf_storage_key` text,
  `pac_provider` varchar(60),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `invoice_items` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `invoice_id` CHAR(36) NOT NULL,
  `line_number` smallint NOT NULL,
  `treatment_id` CHAR(36),
  `quote_item_id` CHAR(36),
  `description` varchar(300) NOT NULL,
  `sat_product_key` varchar(8) NOT NULL,
  `sat_unit_key` varchar(3) NOT NULL,
  `quantity` DECIMAL(14,4) NOT NULL,
  `unit_price` DECIMAL(14,4) NOT NULL,
  `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `amount` DECIMAL(14,2) NOT NULL,
  `tax_object_code` varchar(2) NOT NULL DEFAULT '02' COMMENT 'c_ObjetoImp'
);

CREATE TABLE `invoice_item_taxes` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `invoice_item_id` CHAR(36) NOT NULL,
  `tax_code` varchar(3) NOT NULL,
  `factor_type` varchar(10) NOT NULL COMMENT 'Tasa, Cuota, Exento',
  `rate` DECIMAL(7,6),
  `base` DECIMAL(14,2) NOT NULL,
  `amount` DECIMAL(14,2),
  `is_withholding` boolean NOT NULL DEFAULT false
);

CREATE TABLE `invoice_relations` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `invoice_id` CHAR(36) NOT NULL,
  `related_invoice_id` CHAR(36) NOT NULL,
  `relation_type_code` varchar(2) NOT NULL COMMENT 'c_TipoRelacion (01 nota crédito, 04 sustitución...)'
);

CREATE TABLE `pac_requests` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `invoice_id` CHAR(36) NOT NULL,
  `kind` ENUM ('timbrado', 'cancelacion', 'consulta_estatus') NOT NULL,
  `status` ENUM ('pendiente', 'enviado', 'exitoso', 'error', 'reintento') NOT NULL DEFAULT 'pendiente',
  `idempotency_key` varchar(100) NOT NULL,
  `attempt_number` integer NOT NULL DEFAULT 1,
  `request_payload_ref` text COMMENT 'referencia; evitar guardar datos sensibles',
  `response_code` varchar(40),
  `response_message` text,
  `error_detail` text,
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `responded_at` DATETIME,
  `next_retry_at` DATETIME
);

CREATE TABLE `invoice_cancellations` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `invoice_id` CHAR(36) NOT NULL,
  `reason_code` varchar(2) NOT NULL COMMENT 'c_MotivoCancelacion 01-04',
  `substitute_invoice_id` CHAR(36),
  `status` ENUM ('solicitada', 'aceptada', 'rechazada', 'en_proceso') NOT NULL DEFAULT 'solicitada',
  `requested_by` CHAR(36),
  `requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `response_at` DATETIME,
  `response_detail` text,
  `pac_request_id` CHAR(36)
);

CREATE TABLE `payments` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `patient_id` CHAR(36) NOT NULL,
  `paid_at` DATETIME NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'CHECK > 0',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `method` ENUM ('efectivo', 'tarjeta', 'transferencia', 'domiciliado') NOT NULL,
  `reference` varchar(120),
  `status` ENUM ('registrado', 'revertido') NOT NULL DEFAULT 'registrado',
  `idempotency_key` varchar(100) NOT NULL,
  `cash_session_id` CHAR(36),
  `created_by` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `payment_applications` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `payment_id` CHAR(36) NOT NULL,
  `invoice_id` CHAR(36) NOT NULL,
  `applied_amount` DECIMAL(14,2) NOT NULL COMMENT 'CHECK > 0',
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reversed_at` DATETIME
);

CREATE TABLE `payment_reversals` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `payment_id` CHAR(36) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `reason` text NOT NULL,
  `reversed_by` CHAR(36),
  `reversed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `payment_complements` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `complement_invoice_id` CHAR(36) NOT NULL COMMENT 'CFDI tipo P',
  `payment_id` CHAR(36) NOT NULL,
  `related_invoice_id` CHAR(36) NOT NULL,
  `installment_number` integer,
  `previous_balance` DECIMAL(14,2),
  `paid_amount` DECIMAL(14,2),
  `remaining_balance` DECIMAL(14,2)
);

CREATE TABLE `financial_categories` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'nómina, insumos, renta, servicios, laboratorio, ...',
  `direction` ENUM ('ingreso', 'egreso') NOT NULL,
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `cash_registers` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `name` varchar(100) NOT NULL,
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `cash_sessions` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `cash_register_id` CHAR(36) NOT NULL,
  `opened_by` CHAR(36) NOT NULL,
  `opened_at` DATETIME NOT NULL,
  `opening_amount` DECIMAL(14,2) NOT NULL COMMENT 'CHECK >= 0',
  `status` ENUM ('abierta', 'cerrada') NOT NULL DEFAULT 'abierta',
  `closed_by` CHAR(36),
  `closed_at` DATETIME,
  `expected_cash` DECIMAL(14,2) COMMENT 'opening + efectivo entradas - salidas',
  `counted_cash` DECIMAL(14,2),
  `difference` DECIMAL(14,2),
  `notes` text
);

CREATE TABLE `cash_session_adjustments` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `cash_session_id` CHAR(36) NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL,
  `reason` text NOT NULL,
  `created_by` CHAR(36) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `financial_movements` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36),
  `direction` ENUM ('ingreso', 'egreso') NOT NULL,
  `category_id` CHAR(36) NOT NULL,
  `concept` varchar(300) NOT NULL,
  `occurred_on` date NOT NULL,
  `amount` DECIMAL(14,2) NOT NULL COMMENT 'CHECK > 0',
  `currency` char(3) NOT NULL DEFAULT 'MXN',
  `method` ENUM ('efectivo', 'tarjeta', 'transferencia', 'domiciliado') NOT NULL,
  `payment_id` CHAR(36) COMMENT 'ingresos de pacientes: UNIQUE -> un pago = un movimiento (no duplica ingreso)',
  `cash_session_id` CHAR(36) COMMENT 'solo si method=efectivo',
  `receipt_storage_key` text,
  `created_by` CHAR(36),
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reversed_of_id` CHAR(36)
);

CREATE TABLE `suppliers` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(200) NOT NULL,
  `contact_phone` varchar(30),
  `contact_email` varchar(254),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `inventory_items` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `name` varchar(200) NOT NULL,
  `sku` varchar(60) NOT NULL,
  `category` varchar(100),
  `unit_of_measure` varchar(30) NOT NULL,
  `supplier_id` CHAR(36),
  `status` ENUM ('active', 'inactive') NOT NULL DEFAULT 'active'
);

CREATE TABLE `inventory_stock` (
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NOT NULL,
  `quantity_on_hand` DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'CHECK >= 0 (política: sin stock negativo); actualizado atómicamente por trigger desde movimientos',
  `min_quantity` DECIMAL(14,4) NOT NULL DEFAULT 0,
  `location` varchar(100),
  `avg_unit_cost` DECIMAL(14,4) NOT NULL DEFAULT 0 COMMENT 'valoración: costo promedio ponderado',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`branch_id`, `item_id`)
);

CREATE TABLE `inventory_lots` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NOT NULL,
  `lot_code` varchar(60) NOT NULL,
  `expires_on` date,
  `quantity_on_hand` DECIMAL(14,4) NOT NULL DEFAULT 0
);

CREATE TABLE `inventory_movements` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `branch_id` CHAR(36) NOT NULL,
  `item_id` CHAR(36) NOT NULL,
  `movement_type` ENUM ('inventario_inicial', 'entrada', 'salida', 'ajuste', 'reversion') NOT NULL,
  `quantity` DECIMAL(14,4) NOT NULL COMMENT 'con signo: + entrada, - salida; CHECK <> 0',
  `unit_cost` DECIMAL(14,4),
  `reference` varchar(120),
  `reason` text,
  `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actor_user_id` CHAR(36) NOT NULL,
  `encounter_id` CHAR(36),
  `lot_id` CHAR(36),
  `reverses_movement_id` CHAR(36)
);

CREATE TABLE `notification_preferences` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `event_type` varchar(60) NOT NULL COMMENT 'reminder_24h_email, reminder_2h_whatsapp, appointment_confirmed, appointment_cancelled, low_stock, payment_received',
  `channel` ENUM ('in_app', 'email', 'whatsapp') NOT NULL,
  `is_enabled` boolean NOT NULL DEFAULT true,
  `lead_time_minutes` integer,
  `recipient_role_id` CHAR(36)
);

CREATE TABLE `notifications` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `event_type` varchar(60) NOT NULL,
  `title` varchar(200) NOT NULL,
  `body` text,
  `entity_type` varchar(60),
  `entity_id` CHAR(36),
  `dedup_key` varchar(200) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `notification_recipients` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `notification_id` CHAR(36) NOT NULL,
  `user_id` CHAR(36),
  `patient_id` CHAR(36),
  `channel` ENUM ('in_app', 'email', 'whatsapp') NOT NULL,
  `scheduled_for` DATETIME NOT NULL,
  `read_at` DATETIME
);

CREATE TABLE `outbox_events` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `organization_id` CHAR(36) NOT NULL,
  `event_type` varchar(80) NOT NULL,
  `aggregate_type` varchar(60) NOT NULL,
  `aggregate_id` CHAR(36) NOT NULL,
  `payload` JSON NOT NULL,
  `status` ENUM ('pendiente', 'procesando', 'enviado', 'fallido') NOT NULL DEFAULT 'pendiente',
  `available_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `processed_at` DATETIME,
  `attempts` integer NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE `delivery_attempts` (
  `id` CHAR(36) PRIMARY KEY DEFAULT (UUID()),
  `outbox_event_id` CHAR(36),
  `recipient_id` CHAR(36),
  `channel` ENUM ('in_app', 'email', 'whatsapp') NOT NULL,
  `status` ENUM ('pendiente', 'enviado', 'entregado', 'fallido') NOT NULL DEFAULT 'pendiente',
  `provider_message_id` varchar(200),
  `error_detail` text,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Creación de índices y restricciones
CREATE UNIQUE INDEX `folio_sequences_index_0` ON `folio_sequences` (`organization_id`, `document_type`, `series`);
CREATE UNIQUE INDEX `branches_index_1` ON `branches` (`organization_id`, `id`);
CREATE UNIQUE INDEX `branches_index_2` ON `branches` (`organization_id`, `name`);
CREATE UNIQUE INDEX `uq_one_main_branch` ON `branches` (`organization_id`);
CREATE UNIQUE INDEX `roles_index_4` ON `roles` (`organization_id`, `id`);
CREATE UNIQUE INDEX `roles_index_5` ON `roles` (`organization_id`, `code`);
CREATE UNIQUE INDEX `roles_index_6` ON `roles` (`organization_id`, `name`);
CREATE UNIQUE INDEX `permissions_index_7` ON `permissions` (`module`, `action`);
CREATE UNIQUE INDEX `organization_members_index_8` ON `organization_members` (`organization_id`, `user_id`);
CREATE UNIQUE INDEX `organization_members_index_9` ON `organization_members` (`organization_id`, `id`);
CREATE INDEX `user_sessions_index_10` ON `user_sessions` (`user_id`, `revoked_at`);
CREATE INDEX `role_change_history_index_11` ON `role_change_history` (`organization_id`, `created_at`);
CREATE INDEX `audit_log_index_12` ON `audit_log` (`organization_id`, `entity_type`, `entity_id`);
CREATE INDEX `audit_log_index_13` ON `audit_log` (`organization_id`, `created_at`);
CREATE UNIQUE INDEX `patients_index_14` ON `patients` (`organization_id`, `id`);
CREATE UNIQUE INDEX `patients_index_15` ON `patients` (`organization_id`, `folio`);
CREATE INDEX `idx_patients_name_trgm` ON `patients` (`organization_id`, `full_name`);
CREATE INDEX `patients_index_17` ON `patients` (`organization_id`, `phone`);
CREATE INDEX `patients_index_18` ON `patients` (`organization_id`, `email`);
CREATE UNIQUE INDEX `patient_fiscal_profiles_index_19` ON `patient_fiscal_profiles` (`organization_id`, `id`);
CREATE UNIQUE INDEX `tax_profiles_index_20` ON `tax_profiles` (`organization_id`, `id`);
CREATE UNIQUE INDEX `tax_profiles_index_21` ON `tax_profiles` (`organization_id`, `name`);
CREATE UNIQUE INDEX `treatment_categories_index_22` ON `treatment_categories` (`organization_id`, `id`);
CREATE UNIQUE INDEX `treatment_categories_index_23` ON `treatment_categories` (`organization_id`, `name`);
CREATE UNIQUE INDEX `treatments_index_24` ON `treatments` (`organization_id`, `id`);
CREATE INDEX `treatments_index_25` ON `treatments` (`organization_id`, `name`);
CREATE UNIQUE INDEX `appointments_index_26` ON `appointments` (`organization_id`, `id`);
CREATE INDEX `appointments_index_27` ON `appointments` (`organization_id`, `branch_id`, `starts_at`);
CREATE INDEX `appointments_index_28` ON `appointments` (`organization_id`, `doctor_member_id`, `starts_at`);
CREATE INDEX `appointments_index_29` ON `appointments` (`organization_id`, `patient_id`, `starts_at`);
CREATE INDEX `appointments_index_30` ON `appointments` (`organization_id`, `status`, `starts_at`);
CREATE INDEX `appointment_status_history_index_31` ON `appointment_status_history` (`appointment_id`, `created_at`);
CREATE UNIQUE INDEX `medical_records_index_32` ON `medical_records` (`organization_id`, `id`);
CREATE UNIQUE INDEX `medical_records_index_33` ON `medical_records` (`organization_id`, `patient_id`);
CREATE UNIQUE INDEX `clinical_encounters_index_34` ON `clinical_encounters` (`organization_id`, `id`);
CREATE INDEX `clinical_encounters_index_35` ON `clinical_encounters` (`organization_id`, `medical_record_id`, `occurred_at`);
CREATE UNIQUE INDEX `patient_treatments_index_36` ON `patient_treatments` (`organization_id`, `id`);
CREATE UNIQUE INDEX `clinical_notes_index_37` ON `clinical_notes` (`organization_id`, `id`);
CREATE INDEX `clinical_notes_index_38` ON `clinical_notes` (`organization_id`, `encounter_id`);
CREATE UNIQUE INDEX `clinical_note_addenda_index_39` ON `clinical_note_addenda` (`note_id`, `version_number`);
CREATE INDEX `tooth_findings_index_40` ON `tooth_findings` (`organization_id`, `patient_id`, `tooth_fdi`, `recorded_at`);
CREATE UNIQUE INDEX `tooth_findings_index_41` ON `tooth_findings` (`organization_id`, `patient_id`, `tooth_fdi`);
CREATE UNIQUE INDEX `clinical_files_index_42` ON `clinical_files` (`organization_id`, `id`);
CREATE INDEX `clinical_files_index_43` ON `clinical_files` (`organization_id`, `patient_id`, `file_type`);
CREATE UNIQUE INDEX `clinical_files_index_44` ON `clinical_files` (`organization_id`, `storage_key`);
CREATE UNIQUE INDEX `medications_index_45` ON `medications` (`organization_id`, `id`);
CREATE UNIQUE INDEX `prescriptions_index_46` ON `prescriptions` (`organization_id`, `id`);
CREATE UNIQUE INDEX `prescriptions_index_47` ON `prescriptions` (`organization_id`, `folio`);
CREATE INDEX `prescriptions_index_48` ON `prescriptions` (`organization_id`, `patient_id`, `issued_on`);
CREATE UNIQUE INDEX `prescription_items_index_49` ON `prescription_items` (`prescription_id`, `line_number`);
CREATE UNIQUE INDEX `quotes_index_50` ON `quotes` (`organization_id`, `id`);
CREATE UNIQUE INDEX `quotes_index_51` ON `quotes` (`organization_id`, `folio`);
CREATE INDEX `quotes_index_52` ON `quotes` (`organization_id`, `status`, `sent_at`);
CREATE UNIQUE INDEX `quote_items_index_53` ON `quote_items` (`organization_id`, `id`);
CREATE UNIQUE INDEX `quote_items_index_54` ON `quote_items` (`quote_id`, `line_number`);
CREATE UNIQUE INDEX `invoices_index_55` ON `invoices` (`organization_id`, `id`);
CREATE UNIQUE INDEX `invoices_index_56` ON `invoices` (`organization_id`, `internal_folio`);
CREATE UNIQUE INDEX `invoices_index_57` ON `invoices` (`organization_id`, `uuid_fiscal`);
CREATE UNIQUE INDEX `invoices_index_58` ON `invoices` (`organization_id`, `series`, `fiscal_folio`);
CREATE INDEX `invoices_index_59` ON `invoices` (`organization_id`, `patient_id`);
CREATE INDEX `invoices_index_60` ON `invoices` (`organization_id`, `fiscal_status`, `issued_at`);
CREATE INDEX `invoices_index_61` ON `invoices` (`organization_id`, `collection_status`);
CREATE UNIQUE INDEX `invoice_items_index_62` ON `invoice_items` (`organization_id`, `id`);
CREATE UNIQUE INDEX `invoice_items_index_63` ON `invoice_items` (`invoice_id`, `line_number`);
CREATE UNIQUE INDEX `pac_requests_index_64` ON `pac_requests` (`organization_id`, `idempotency_key`);
CREATE INDEX `pac_requests_index_65` ON `pac_requests` (`organization_id`, `invoice_id`);
CREATE INDEX `pac_requests_index_66` ON `pac_requests` (`status`, `next_retry_at`);
CREATE UNIQUE INDEX `payments_index_67` ON `payments` (`organization_id`, `id`);
CREATE UNIQUE INDEX `payments_index_68` ON `payments` (`organization_id`, `idempotency_key`);
CREATE INDEX `payments_index_69` ON `payments` (`organization_id`, `method`, `reference`);
CREATE INDEX `payments_index_70` ON `payments` (`organization_id`, `paid_at`);
CREATE INDEX `payment_applications_index_71` ON `payment_applications` (`organization_id`, `invoice_id`);
CREATE INDEX `payment_applications_index_72` ON `payment_applications` (`organization_id`, `payment_id`);
CREATE UNIQUE INDEX `financial_categories_index_73` ON `financial_categories` (`organization_id`, `id`);
CREATE UNIQUE INDEX `financial_categories_index_74` ON `financial_categories` (`organization_id`, `name`, `direction`);
CREATE UNIQUE INDEX `cash_registers_index_75` ON `cash_registers` (`organization_id`, `id`);
CREATE UNIQUE INDEX `cash_registers_index_76` ON `cash_registers` (`organization_id`, `branch_id`, `name`);
CREATE UNIQUE INDEX `cash_sessions_index_77` ON `cash_sessions` (`organization_id`, `id`);
CREATE UNIQUE INDEX `cash_sessions_index_78` ON `cash_sessions` (`cash_register_id`);
CREATE UNIQUE INDEX `financial_movements_index_79` ON `financial_movements` (`organization_id`, `id`);
CREATE UNIQUE INDEX `financial_movements_index_80` ON `financial_movements` (`organization_id`, `payment_id`);
CREATE INDEX `financial_movements_index_81` ON `financial_movements` (`organization_id`, `occurred_on`, `direction`);
CREATE INDEX `financial_movements_index_82` ON `financial_movements` (`organization_id`, `category_id`);
CREATE UNIQUE INDEX `suppliers_index_83` ON `suppliers` (`organization_id`, `id`);
CREATE UNIQUE INDEX `inventory_items_index_84` ON `inventory_items` (`organization_id`, `id`);
CREATE UNIQUE INDEX `inventory_items_index_85` ON `inventory_items` (`organization_id`, `sku`);
CREATE INDEX `inventory_items_index_86` ON `inventory_items` (`organization_id`, `name`);
CREATE UNIQUE INDEX `inventory_lots_index_87` ON `inventory_lots` (`organization_id`, `id`);
CREATE UNIQUE INDEX `inventory_lots_index_88` ON `inventory_lots` (`branch_id`, `item_id`, `lot_code`);
CREATE UNIQUE INDEX `inventory_movements_index_89` ON `inventory_movements` (`organization_id`, `id`);
CREATE INDEX `inventory_movements_index_90` ON `inventory_movements` (`organization_id`, `branch_id`, `item_id`, `occurred_at`);
CREATE UNIQUE INDEX `inventory_movements_index_91` ON `inventory_movements` (`branch_id`, `item_id`);
CREATE UNIQUE INDEX `notification_preferences_index_92` ON `notification_preferences` (`organization_id`, `event_type`, `channel`);
CREATE UNIQUE INDEX `notifications_index_93` ON `notifications` (`organization_id`, `id`);
CREATE UNIQUE INDEX `notifications_index_94` ON `notifications` (`organization_id`, `dedup_key`);
CREATE INDEX `notification_recipients_index_95` ON `notification_recipients` (`user_id`, `read_at`);
CREATE INDEX `notification_recipients_index_96` ON `notification_recipients` (`scheduled_for`);
CREATE INDEX `outbox_events_index_97` ON `outbox_events` (`status`, `available_at`);
CREATE INDEX `outbox_events_index_98` ON `outbox_events` (`organization_id`, `aggregate_type`, `aggregate_id`);

ALTER TABLE `organization_fiscal_settings` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `folio_sequences` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `branches` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `roles` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `role_permissions` ADD FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`);
ALTER TABLE `role_permissions` ADD FOREIGN KEY (`organization_id`, `role_id`) REFERENCES `roles` (`organization_id`, `id`);
ALTER TABLE `organization_members` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `organization_members` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `organization_members` ADD FOREIGN KEY (`organization_id`, `role_id`) REFERENCES `roles` (`organization_id`, `id`);
ALTER TABLE `member_branches` ADD FOREIGN KEY (`organization_id`, `member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `member_branches` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);

CREATE UNIQUE INDEX `doctor_profiles_idx_fk` ON `doctor_profiles` (`organization_id`, `member_id`);
ALTER TABLE `organization_members` ADD FOREIGN KEY (`organization_id`, `id`) REFERENCES `doctor_profiles` (`organization_id`, `member_id`);

ALTER TABLE `user_invitations` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `user_invitations` ADD FOREIGN KEY (`organization_id`, `role_id`) REFERENCES `roles` (`organization_id`, `id`);
ALTER TABLE `user_sessions` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `user_sessions` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `role_change_history` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `role_change_history` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `audit_log` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `audit_log` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `patients` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `patients` ADD FOREIGN KEY (`organization_id`, `assigned_doctor_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `patient_fiscal_profiles` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `patient_guardians` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `tax_profiles` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `treatment_categories` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `treatments` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `treatments` ADD FOREIGN KEY (`organization_id`, `category_id`) REFERENCES `treatment_categories` (`organization_id`, `id`);
ALTER TABLE `treatments` ADD FOREIGN KEY (`organization_id`, `tax_profile_id`) REFERENCES `tax_profiles` (`organization_id`, `id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`organization_id`, `doctor_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `appointments` ADD FOREIGN KEY (`organization_id`, `treatment_id`) REFERENCES `treatments` (`organization_id`, `id`);
ALTER TABLE `appointment_status_history` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `appointment_status_history` ADD FOREIGN KEY (`organization_id`, `appointment_id`) REFERENCES `appointments` (`organization_id`, `id`);
ALTER TABLE `medical_records` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `clinical_encounters` ADD FOREIGN KEY (`organization_id`, `medical_record_id`) REFERENCES `medical_records` (`organization_id`, `id`);
ALTER TABLE `clinical_encounters` ADD FOREIGN KEY (`organization_id`, `appointment_id`) REFERENCES `appointments` (`organization_id`, `id`);
ALTER TABLE `clinical_encounters` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `clinical_encounters` ADD FOREIGN KEY (`organization_id`, `doctor_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `patient_treatments` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `patient_treatments` ADD FOREIGN KEY (`organization_id`, `treatment_id`) REFERENCES `treatments` (`organization_id`, `id`);
ALTER TABLE `clinical_notes` ADD FOREIGN KEY (`organization_id`, `encounter_id`) REFERENCES `clinical_encounters` (`organization_id`, `id`);
ALTER TABLE `clinical_notes` ADD FOREIGN KEY (`organization_id`, `author_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `clinical_notes` ADD FOREIGN KEY (`organization_id`, `treatment_id`) REFERENCES `treatments` (`organization_id`, `id`);
ALTER TABLE `clinical_notes` ADD FOREIGN KEY (`organization_id`, `appointment_id`) REFERENCES `appointments` (`organization_id`, `id`);
ALTER TABLE `clinical_note_addenda` ADD FOREIGN KEY (`organization_id`, `note_id`) REFERENCES `clinical_notes` (`organization_id`, `id`);
ALTER TABLE `tooth_findings` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `tooth_findings` ADD FOREIGN KEY (`organization_id`, `encounter_id`) REFERENCES `clinical_encounters` (`organization_id`, `id`);
ALTER TABLE `tooth_findings` ADD FOREIGN KEY (`organization_id`, `patient_treatment_id`) REFERENCES `patient_treatments` (`organization_id`, `id`);
ALTER TABLE `clinical_files` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `clinical_files` ADD FOREIGN KEY (`organization_id`, `encounter_id`) REFERENCES `clinical_encounters` (`organization_id`, `id`);
ALTER TABLE `consent_acceptances` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `consent_acceptances` ADD FOREIGN KEY (`organization_id`, `clinical_file_id`) REFERENCES `clinical_files` (`organization_id`, `id`);
ALTER TABLE `medications` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `prescriptions` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `prescriptions` ADD FOREIGN KEY (`organization_id`, `doctor_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `prescriptions` ADD FOREIGN KEY (`organization_id`, `repeated_from_id`) REFERENCES `prescriptions` (`organization_id`, `id`);
ALTER TABLE `prescription_items` ADD FOREIGN KEY (`organization_id`, `prescription_id`) REFERENCES `prescriptions` (`organization_id`, `id`);
ALTER TABLE `prescription_items` ADD FOREIGN KEY (`organization_id`, `medication_id`) REFERENCES `medications` (`organization_id`, `id`);
ALTER TABLE `prescription_deliveries` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `prescription_deliveries` ADD FOREIGN KEY (`organization_id`, `prescription_id`) REFERENCES `prescriptions` (`organization_id`, `id`);
ALTER TABLE `quotes` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `quotes` ADD FOREIGN KEY (`organization_id`, `doctor_member_id`) REFERENCES `organization_members` (`organization_id`, `id`);
ALTER TABLE `quotes` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `quote_items` ADD FOREIGN KEY (`organization_id`, `quote_id`) REFERENCES `quotes` (`organization_id`, `id`);
ALTER TABLE `quote_items` ADD FOREIGN KEY (`organization_id`, `treatment_id`) REFERENCES `treatments` (`organization_id`, `id`);
ALTER TABLE `patient_treatments` ADD FOREIGN KEY (`organization_id`, `quote_item_id`) REFERENCES `quote_items` (`organization_id`, `id`);
ALTER TABLE `quote_status_history` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `quote_status_history` ADD FOREIGN KEY (`organization_id`, `quote_id`) REFERENCES `quotes` (`organization_id`, `id`);
ALTER TABLE `invoices` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `invoices` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `invoices` ADD FOREIGN KEY (`organization_id`, `quote_id`) REFERENCES `quotes` (`organization_id`, `id`);
ALTER TABLE `invoice_items` ADD FOREIGN KEY (`organization_id`, `invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `invoice_items` ADD FOREIGN KEY (`organization_id`, `treatment_id`) REFERENCES `treatments` (`organization_id`, `id`);
ALTER TABLE `invoice_items` ADD FOREIGN KEY (`organization_id`, `quote_item_id`) REFERENCES `quote_items` (`organization_id`, `id`);
ALTER TABLE `invoice_item_taxes` ADD FOREIGN KEY (`organization_id`, `invoice_item_id`) REFERENCES `invoice_items` (`organization_id`, `id`);
ALTER TABLE `invoice_relations` ADD FOREIGN KEY (`organization_id`, `invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `invoice_relations` ADD FOREIGN KEY (`organization_id`, `related_invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `pac_requests` ADD FOREIGN KEY (`organization_id`, `invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `invoice_cancellations` ADD FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`);
ALTER TABLE `invoice_cancellations` ADD FOREIGN KEY (`organization_id`, `invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `invoice_cancellations` ADD FOREIGN KEY (`organization_id`, `substitute_invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `payments` ADD FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `payments` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `payments` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `payment_applications` ADD FOREIGN KEY (`organization_id`, `payment_id`) REFERENCES `payments` (`organization_id`, `id`);
ALTER TABLE `payment_applications` ADD FOREIGN KEY (`organization_id`, `invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `payment_reversals` ADD FOREIGN KEY (`reversed_by`) REFERENCES `users` (`id`);
ALTER TABLE `payment_reversals` ADD FOREIGN KEY (`organization_id`, `payment_id`) REFERENCES `payments` (`organization_id`, `id`);
ALTER TABLE `payment_complements` ADD FOREIGN KEY (`organization_id`, `complement_invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `payment_complements` ADD FOREIGN KEY (`organization_id`, `payment_id`) REFERENCES `payments` (`organization_id`, `id`);
ALTER TABLE `payment_complements` ADD FOREIGN KEY (`organization_id`, `related_invoice_id`) REFERENCES `invoices` (`organization_id`, `id`);
ALTER TABLE `financial_categories` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `cash_registers` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `cash_sessions` ADD FOREIGN KEY (`opened_by`) REFERENCES `users` (`id`);
ALTER TABLE `cash_sessions` ADD FOREIGN KEY (`closed_by`) REFERENCES `users` (`id`);
ALTER TABLE `cash_sessions` ADD FOREIGN KEY (`organization_id`, `cash_register_id`) REFERENCES `cash_registers` (`organization_id`, `id`);
ALTER TABLE `payments` ADD FOREIGN KEY (`organization_id`, `cash_session_id`) REFERENCES `cash_sessions` (`organization_id`, `id`);
ALTER TABLE `cash_session_adjustments` ADD FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `cash_session_adjustments` ADD FOREIGN KEY (`organization_id`, `cash_session_id`) REFERENCES `cash_sessions` (`organization_id`, `id`);
ALTER TABLE `financial_movements` ADD FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);
ALTER TABLE `financial_movements` ADD FOREIGN KEY (`organization_id`, `category_id`) REFERENCES `financial_categories` (`organization_id`, `id`);
ALTER TABLE `financial_movements` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `payments` ADD FOREIGN KEY (`organization_id`, `id`) REFERENCES `financial_movements` (`organization_id`, `payment_id`);
ALTER TABLE `financial_movements` ADD FOREIGN KEY (`organization_id`, `cash_session_id`) REFERENCES `cash_sessions` (`organization_id`, `id`);
ALTER TABLE `financial_movements` ADD FOREIGN KEY (`organization_id`, `reversed_of_id`) REFERENCES `financial_movements` (`organization_id`, `id`);
ALTER TABLE `suppliers` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `inventory_items` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `inventory_items` ADD FOREIGN KEY (`organization_id`, `supplier_id`) REFERENCES `suppliers` (`organization_id`, `id`);
ALTER TABLE `inventory_stock` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `inventory_stock` ADD FOREIGN KEY (`organization_id`, `item_id`) REFERENCES `inventory_items` (`organization_id`, `id`);
ALTER TABLE `inventory_lots` ADD FOREIGN KEY (`organization_id`, `item_id`) REFERENCES `inventory_items` (`organization_id`, `id`);
ALTER TABLE `inventory_lots` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`organization_id`, `item_id`) REFERENCES `inventory_items` (`organization_id`, `id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`organization_id`, `branch_id`) REFERENCES `branches` (`organization_id`, `id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`organization_id`, `encounter_id`) REFERENCES `clinical_encounters` (`organization_id`, `id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`organization_id`, `lot_id`) REFERENCES `inventory_lots` (`organization_id`, `id`);
ALTER TABLE `inventory_movements` ADD FOREIGN KEY (`organization_id`, `reverses_movement_id`) REFERENCES `inventory_movements` (`organization_id`, `id`);
ALTER TABLE `notification_preferences` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `notifications` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `notification_recipients` ADD FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
ALTER TABLE `notification_recipients` ADD FOREIGN KEY (`organization_id`, `notification_id`) REFERENCES `notifications` (`organization_id`, `id`);
ALTER TABLE `notification_recipients` ADD FOREIGN KEY (`organization_id`, `patient_id`) REFERENCES `patients` (`organization_id`, `id`);
ALTER TABLE `outbox_events` ADD FOREIGN KEY (`organization_id`) REFERENCES `organizations` (`id`);
ALTER TABLE `delivery_attempts` ADD FOREIGN KEY (`outbox_event_id`) REFERENCES `outbox_events` (`id`);
ALTER TABLE `delivery_attempts` ADD FOREIGN KEY (`recipient_id`) REFERENCES `notification_recipients` (`id`);