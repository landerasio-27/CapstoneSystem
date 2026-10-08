<?php

/*
 * The admin navigation: sections → items, in display order.
 *
 * Every entry points at an existing route — nothing here is a placeholder.
 * `role` lists who sees the item; when omitted the item is visible to both
 * office roles, which is every item today — the back office (users, the
 * activity log, the archive) is reached from the settings page rather than
 * from this menu, and the routes behind it guard themselves.
 *
 * `queries` turns one route into several destinations (e.g. the Documents
 * index filtered per type). An item that carries queries is active only when
 * the request matches them; the plain item yields to the variant.
 *
 * `keywords` feed the navigation search: extra words a person may type that
 * should surface the item ("slip" → RIS, "label" → QR Codes).
 */

return [
    /*
     * `top` renders as a standalone link above the collapsible sections —
     * no group header, just the item. The dashboard lives here: it is the
     * one page that needs no section of its own.
     */
    'top' => [
        [
            'label' => 'Dashboard', 'route' => 'admin.dashboard', 'icon' => 'grid',
            'keywords' => 'home start summary overview analytics',
        ],
    ],

    'sections' => [
        'Inventory' => [
            [
                'label' => 'Supplies', 'route' => 'admin.supplies.index', 'icon' => 'archive',
                'keywords' => 'consumables materials stock office items inventory',
            ],
            [
                'label' => 'Equipment', 'route' => 'admin.equipment.index', 'icon' => 'box',
                'keywords' => 'property ppe assets machines devices ict',
            ],
        ],

        /*
         * Everything paper-shaped lives under one collapsible group: the
         * document register and the cards register. Stock Cards and Property
         * Cards are tabs of the cards register itself, not separate pages, so
         * they are reached from there rather than listed again here.
         */
        'Documents' => [
            /*
             * The register is where the office's papers are raised, approved
             * and issued, and the cards they post to are the office's own
             * stock and property ledger, so the supply staff work both
             * alongside the administrator.
             */
            [
                'label' => 'PAR / ICS / RIS', 'route' => 'admin.documents.index', 'icon' => 'file',
                'keywords' => 'all documents register par ics ris iar papers requisition issue slip '
                    . 'property acknowledgment receipt inventory custodian inspection acceptance report',
            ],
            [
                'label' => 'Stock / Property', 'route' => 'admin.cards.index', 'icon' => 'cards',
                'keywords' => 'all cards add card register stock cards property cards sc pc ledger '
                    . 'balance received issued transfer disposal list',
            ],
        ],

        /*
         * Places, people and labels: the reference data the inventory points
         * at, kept apart from the stock items themselves.
         */
        'Organization' => [
            [
                'label' => 'Rooms', 'route' => 'admin.rooms.index', 'icon' => 'door',
                'keywords' => 'laboratory building floor location assignment storage',
            ],
            [
                'label' => 'Custodian Folders', 'route' => 'admin.teachers.index', 'icon' => 'folder',
                'keywords' => 'folder folders faculty employees staff personnel teacher directory custody',
            ],
        ],

        'Reports' => [
            /*
             * The official RIS sheet: the supply staff print the same paper the
             * register holds, so it is offered to them. The summary and
             * register reports below stay the administrator's.
             */
            [
                'label' => 'Monthly Report', 'route' => 'admin.reports.monthly.index', 'icon' => 'clipboard',
                'keywords' => 'monthly report per end user blank form manual input fill in nothing follows '
                    . 'ris supplies and materials issued print pdf',
            ],
            [
                'label' => 'Inventory Summary', 'route' => 'admin.reports.inventory-summary', 'icon' => 'archive',
                'keywords' => 'inventory report stock on hand per item',
            ],
        ],

        /*
         * One door to the back office. Users, the archive years and the
         * activity log are panels of the settings page itself, so they are not
         * listed here as well: the sidebar carries the page, and the page
         * carries the sections.
         */
        'Administration' => [
            [
                'label' => 'Settings', 'route' => 'admin.account-settings.index', 'icon' => 'settings',
                'keywords' => 'profile password account system settings preferences users archive activity logs',
            ],
        ],
    ],

    /*
     * Breadcrumb labels for resources whose route segment is not a friendly
     * name whose segment is not already a friendly label (admin.documents.* →
     * "PAR / ICS / RIS"). Everything else is derived from the segment.
     */
    'resource_labels' => [
        'activity-logs' => 'Activity Logs',
        'archive' => 'Archive',
        'documents' => 'PAR / ICS / RIS',
        'equipment' => 'Equipment',
        'supplies' => 'Supplies',
        // The sidebar item keeps its short name; the pages behind it are the
        // folder interface, and the breadcrumb says so.
        'teachers' => 'Custodian Folders',
        'rooms' => 'Rooms',
        'users' => 'Users',
        'reports' => 'Reports',
        'cards' => 'Stock / Property',
    ],
];
