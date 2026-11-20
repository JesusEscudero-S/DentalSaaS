<?php

namespace App\Enums;

/**
 * Plantillas del módulo Documentos. No hay tabla: cada documento generado es un clinical_files cuyo
 * storage_key sigue org/{org}/documentos/{template}/{document}/documento.pdf.
 */
enum DocumentTemplate: string
{
    /** Texto fijo de la Autorización de Tratamiento: se muestra en el formulario y en el PDF. */
    public const CONSENT_DECLARATION = 'Declaro que el odontólogo tratante me explicó de manera clara y comprensible el diagnóstico, '
        .'el plan de tratamiento propuesto y sus alternativas, los riesgos y posibles complicaciones, así como el costo '
        .'estimado. Tuve la oportunidad de hacer preguntas y fueron respondidas a mi satisfacción. Por lo anterior, '
        .'autorizo de forma libre y voluntaria la realización del tratamiento descrito en este documento. Si el paciente '
        .'es menor de edad o no puede otorgar su consentimiento, firma su padre, madre o tutor legal.';

    case MedicalHistory = 'historia-clinica';
    case TreatmentAuthorization = 'autorizacion-tratamiento';

    public function label(): string
    {
        return match ($this) {
            self::MedicalHistory => 'Historia Clínica Dental',
            self::TreatmentAuthorization => 'Autorización de Tratamiento',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MedicalHistory => 'Plantilla estandarizada para registrar los antecedentes médicos, odontograma inicial, padecimientos actuales y plan de tratamiento detallado.',
            self::TreatmentAuthorization => 'Genera un formulario de consentimiento informado. Obligatorio para asegurar que el paciente comprende y acepta el plan y presupuesto de los procedimientos clínicos.',
        };
    }

    public function fileType(): ClinicalFileType
    {
        return match ($this) {
            self::MedicalHistory => ClinicalFileType::Other,
            self::TreatmentAuthorization => ClinicalFileType::Consent,
        };
    }

    /** Atributo `d` del path SVG (viewBox 0 0 24 24, trazo). */
    public function icon(): string
    {
        return match ($this) {
            self::MedicalHistory => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            self::TreatmentAuthorization => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4',
        };
    }

    /** Color de acento (de Ui::COLORS). */
    public function color(): string
    {
        return match ($this) {
            self::MedicalHistory => 'blue',
            self::TreatmentAuthorization => 'violet',
        };
    }

    public function requiresSignature(): bool
    {
        return $this === self::TreatmentAuthorization;
    }

    /** Ruta del formulario para crear (o crear una nueva versión de) este documento. */
    public function createRoute(): string
    {
        return match ($this) {
            self::MedicalHistory => 'documents.medical-history.create',
            self::TreatmentAuthorization => 'documents.authorization.create',
        };
    }

    /** Vista Blade que DomPDF convierte en el PDF. */
    public function pdfView(): string
    {
        return match ($this) {
            self::MedicalHistory => 'documents.pdf.medical-history',
            self::TreatmentAuthorization => 'documents.pdf.treatment-authorization',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $c) => [$c->value => $c->label()])->all();
    }
}
