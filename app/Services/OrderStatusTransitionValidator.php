<?php

namespace App\Services;

use App\Enums\OrderItemStatus;
use App\Enums\PipelineStage;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Concerns\HasStatusTransitionRules;
use Exception;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Webkul\Lead\Models\Stage;

class OrderStatusTransitionValidator
{
    use HasStatusTransitionRules;

    /**
     * Validatie regels per status transitie.
     * Key format: "from_stage_code->to_stage_code"
     *
     * @var array<string, array<string,mixed>>
     */
    private static array $transitionRules = [];

    /**
     * Indicates whether default transition rules have been registered.
     */
    private static bool $defaultsInitialized = false;

    /**
     * Valideer een status transitie voor een order.
     *
     * @throws ValidationException
     */
    public static function validateTransition(Order $order, int $newStageId): void
    {
        // Lazily register default rules
        self::ensureDefaultRules();

        $currentStage = $order->pipeline_stage_id
            ? Stage::find($order->pipeline_stage_id)
            : null;
        $newStage = Stage::findOrFail($newStageId);

        if (! $currentStage) {
            // Nieuwe order zonder huidige stage: geen transitie validatie nodig
            return;
        }

        $transitionKey = $currentStage->code.'->'.$newStage->code;

        // Specifieke regel eerst
        if (isset(self::$transitionRules[$transitionKey])) {
            $rules = self::$transitionRules[$transitionKey];
        }
        // Vervolgens wildcard regels, bijvoorbeeld *->order-ingepland
        elseif (isset(self::$transitionRules['*->'.$newStage->code])) {
            $rules = self::$transitionRules['*->'.$newStage->code];
        } else {
            return; // Geen validatie regels voor deze transitie
        }

        $errors = [];

        // Valideer verplichte velden (indien geconfigureerd)
        if (isset($rules['required_fields'])) {
            foreach ($rules['required_fields'] as $field) {
                if (empty($order->$field)) {
                    $errors[] = "Het veld '{$field}' is verplicht voor deze order status.";
                }
            }
        }

        // Valideer custom regels
        if (isset($rules['custom_validation'])) {
            $customErrors = self::executeCustomValidation($order, $rules['custom_validation']);
            $errors = array_merge($errors, $customErrors);
        }

        if (! empty($errors)) {
            $validator = Validator::make([], []);

            foreach ($errors as $error) {
                $validator->errors()->add('status_transition', $error);
            }

            throw new ValidationException($validator);
        }
    }

    /**
     * Zorg dat de default regels aanwezig zijn (lazy init).
     */
    private static function ensureDefaultRules(): void
    {
        if (self::$defaultsInitialized) {
            return;
        }

        // Algemene regel:
        // Voor alle order statussen, behalve ORDER_CONFIRM en ORDER_VOORBEREIDEN_HERNIA,
        // moeten alle plannable order items een status hebben die niet NEW is
        // (dus PLANNED, WON of LOST). Niet-plannable items worden genegeerd.
        $targetStages = array_filter(
            PipelineStage::cases(),
            static fn (PipelineStage $stage) => $stage->isOrder()
                && ! in_array($stage, [
                    PipelineStage::ORDER_CONFIRM,
                    PipelineStage::ORDER_VOORBEREIDEN_HERNIA,
                ], true)
        );

        self::addWildcardToStagesRules(
            $targetStages,
            [
                'custom_validation' => static function (Order $order): array {
                    return self::validatePlannableItemsNotNew($order);
                },
            ]
        );

        self::$defaultsInitialized = true;
    }

    /**
     * Voer custom validatie uit.
     *
     * @param  callable(Order): (array|string|null)  $validationFunction
     */
    private static function executeCustomValidation(Order $order, callable $validationFunction): array
    {
        try {
            $result = $validationFunction($order);

            if (is_array($result)) {
                return $result;
            }

            if (is_string($result) && $result !== '') {
                return [$result];
            }

            return [];
        } catch (Exception $e) {
            return ['Validatie fout: '.$e->getMessage()];
        }
    }

    /**
     * Controleer dat alle plannable order items niet in status NEW staan.
     *
     * Alleen producten die ingepland kunnen worden (op basis van PartnerProduct::isPlannable via OrderItem::isPlannable)
     * tellen mee. Dit sluit aan op de bestaande business logica.
     */
    private static function validatePlannableItemsNotNew(Order $order): array
    {
        // Zorg dat de relaties geladen zijn zodat isPlannable() geen N+1 veroorzaakt.
        $order->loadMissing([
            'orderItems.product.partnerProducts.resourceType',
        ]);

        /** @var \Illuminate\Support\Collection<int,OrderItem> $items */
        $items = $order->orderItems;

        $violatingItems = $items->filter(
            static function (OrderItem $item): bool {
                if (! $item->isPlannable()) {
                    return false;
                }

                return $item->status === OrderItemStatus::NEW;
            }
        );

        if ($violatingItems->isEmpty()) {
            return [];
        }

        $productNames = $violatingItems
            ->map(static fn (OrderItem $item): string => $item->getProductName())
            ->filter()
            ->values()
            ->all();

        $nameList = ! empty($productNames)
            ? ' ('.implode(', ', $productNames).')'
            : '';

        return [
            'Order kan niet naar deze status gezet worden: er zijn nog inplanbare orderregels met status "nieuw"'.$nameList.'.',
        ];
    }
}
