<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Supply;

/**
 * The one place that decides which paper an item is recorded on.
 *
 * The office asks the consumable question first and the price question second:
 *
 *   consumable                                     -> RIS (Requisition and Issue Slip)
 *   non-consumable, above the threshold            -> PAR (Property Acknowledgement Receipt)
 *   non-consumable, at or below the threshold      -> ICS (Inventory Custodian Slip)
 *
 * Because consumability decides first, a ₱500 box of ballpens is still a RIS:
 * the unit cost only separates the two property papers from each other. Every
 * screen, badge, form and save reads the rule from here — it is deliberately
 * never re-implemented per page, so the classification cannot disagree with
 * itself.
 */
class ItemClassifier
{
    /**
     * The Property Classification Threshold: unit cost at or below which
     * non-consumable property is recorded on an ICS rather than a PAR. Above it
     * the item is a PAR.
     */
    public const PROPERTY_THRESHOLD = 50000.00;

    /** The three papers an item can belong on, in the order the office reads them. */
    public const TYPES = ['PAR', 'ICS', 'RIS'];

    /**
     * The rule itself: consumable -> RIS, otherwise the unit cost decides
     * between a PAR and an ICS.
     */
    public static function for(bool $consumable, float|int|string|null $unitCost): string
    {
        if ($consumable) {
            return 'RIS';
        }

        return (float) $unitCost > self::PROPERTY_THRESHOLD ? 'PAR' : 'ICS';
    }

    /** Which paper the given catalogue item belongs on. */
    public static function forItem(Supply|Equipment $item): string
    {
        return self::for(self::isConsumable($item), $item->unit_cost);
    }

    /**
     * Whether the item is consumed in operations. Supplies are consumables
     * unless the office says otherwise (a semi-expendable item kept in the
     * supply room); equipment is property.
     */
    public static function isConsumable(Supply|Equipment $item): bool
    {
        if ($item->is_consumable !== null) {
            return (bool) $item->is_consumable;
        }

        return $item instanceof Supply;
    }

    /** The paper's full name, as printed on the form and read in the panels. */
    public static function label(string $type): string
    {
        return match ($type) {
            'PAR' => 'Property Acknowledgement Receipt',
            'ICS' => 'Inventory Custodian Slip',
            'RIS' => 'Requisition and Issue Slip',
            'IAR' => 'Inspection and Acceptance Report',
            default => $type,
        };
    }

    /** What the classification means, in two or three words. */
    public static function summaryLabel(string $type): string
    {
        return match ($type) {
            'PAR', 'ICS' => 'Non-Consumable Property',
            'RIS' => 'Consumable Supply',
            default => '',
        };
    }

    /** The reason the item lands on that paper, shown beside the classification. */
    public static function reason(string $type): string
    {
        return match ($type) {
            'PAR' => 'Non-consumable with a unit cost above ₱' . number_format(self::PROPERTY_THRESHOLD, 2) . '.',
            'ICS' => 'Non-consumable with a unit cost of ₱' . number_format(self::PROPERTY_THRESHOLD, 2) . ' or below.',
            'RIS' => 'Consumable — used up, depleted or consumed in operations.',
            default => '',
        };
    }

    /** The subtle pill tone for a classification, shared by every badge. */
    public static function tone(string $type): string
    {
        return match ($type) {
            'PAR' => 'bg-brand-50 text-brand-700 ring-brand-200',
            'ICS' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'RIS' => 'bg-amber-50 text-amber-700 ring-amber-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }

    /**
     * Everything a view (or a live panel's script) says about a classification:
     * its name, what it means, why the item landed there, and its tone.
     *
     * @return array<string, array{type:string,label:string,summary:string,reason:string,tone:string}>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::TYPES as $type) {
            $labels[$type] = [
                'type' => $type,
                'label' => self::label($type),
                'summary' => self::summaryLabel($type),
                'reason' => self::reason($type),
                'tone' => self::tone($type),
            ];
        }

        return $labels;
    }

    /**
     * One line describing a set of classifications, for the save message:
     * "PAR (2 items) and RIS (1 item)".
     *
     * @param  array<string,int>  $counts  classification => number of lines
     */
    public static function describe(array $counts): string
    {
        $parts = [];

        foreach ($counts as $type => $count) {
            $parts[] = sprintf('%s (%d %s)', $type, $count, $count === 1 ? 'item' : 'items');
        }

        return implode(' and ', $parts);
    }
}
