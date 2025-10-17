<?php

namespace App\Enums;

enum ReportingType: string
{
    case FINANCIAL_SUMMARY = 'financial_summary';
    case OPERATIONAL_METRICS = 'operational_metrics';
    case CLINICAL_OUTCOMES = 'clinical_outcomes';
    case PATIENT_SATISFACTION = 'patient_satisfaction';
    case QUALITY_INDICATORS = 'quality_indicators';
    case COMPLIANCE_REPORT = 'compliance_report';
    case PERFORMANCE_ANALYSIS = 'performance_analysis';
    case COST_ANALYSIS = 'cost_analysis';
    case UTILIZATION_REPORT = 'utilization_report';
    case OUTCOME_TRACKING = 'outcome_tracking';

    public function getLabel(): string
    {
        return match($this) {
            self::FINANCIAL_SUMMARY => 'Financial Summary',
            self::OPERATIONAL_METRICS => 'Operational Metrics',
            self::CLINICAL_OUTCOMES => 'Clinical Outcomes',
            self::PATIENT_SATISFACTION => 'Patient Satisfaction',
            self::QUALITY_INDICATORS => 'Quality Indicators',
            self::COMPLIANCE_REPORT => 'Compliance Report',
            self::PERFORMANCE_ANALYSIS => 'Performance Analysis',
            self::COST_ANALYSIS => 'Cost Analysis',
            self::UTILIZATION_REPORT => 'Utilization Report',
            self::OUTCOME_TRACKING => 'Outcome Tracking',
        };
    }

    public function getDescription(): string
    {
        return match($this) {
            self::FINANCIAL_SUMMARY => 'Revenue, costs, and profitability metrics',
            self::OPERATIONAL_METRICS => 'Efficiency, capacity, and operational KPIs',
            self::CLINICAL_OUTCOMES => 'Treatment results and clinical effectiveness',
            self::PATIENT_SATISFACTION => 'Patient feedback and satisfaction scores',
            self::QUALITY_INDICATORS => 'Quality measures and benchmarks',
            self::COMPLIANCE_REPORT => 'Regulatory compliance and audit results',
            self::PERFORMANCE_ANALYSIS => 'Overall performance and trends',
            self::COST_ANALYSIS => 'Detailed cost breakdown and analysis',
            self::UTILIZATION_REPORT => 'Resource utilization and capacity metrics',
            self::OUTCOME_TRACKING => 'Long-term outcome tracking and trends',
        };
    }

    public static function getOptions(): array
    {
        return collect(self::cases())->map(function ($case) {
            return [
                'value' => $case->value,
                'label' => $case->getLabel(),
                'description' => $case->getDescription(),
            ];
        })->toArray();
    }

    public static function getLabels(): array
    {
        return collect(self::cases())->mapWithKeys(function ($case) {
            return [$case->value => $case->getLabel()];
        })->toArray();
    }
}