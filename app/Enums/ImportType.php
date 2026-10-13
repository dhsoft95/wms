<?php

declare(strict_types=1);

namespace App\Enums;

enum ImportType: string
{
    case DepartmentsVws  = 'departments_vws';
    case Partners        = 'partners';
    case ItemCategories  = 'item_categories';
    case Items           = 'items';
    case Locations       = 'locations';
    case OpeningStock           = 'opening_stock';
    case ContainerPackingList  = 'container_packing_list';
    case ActiveContainers      = 'active_containers';

    public function label(): string
    {
        return match($this) {
            self::DepartmentsVws => 'Departments & Virtual Warehouses',
            self::Partners       => 'Partners',
            self::ItemCategories => 'Item Categories',
            self::Items          => 'Items',
            self::Locations      => 'Locations',
            self::OpeningStock          => 'Opening Stock',
            self::ContainerPackingList  => 'Container Packing List',
            self::ActiveContainers      => 'Active Containers',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::DepartmentsVws => 'building',
            self::Partners       => 'users',
            self::ItemCategories => 'tag',
            self::Items          => 'cube',
            self::Locations      => 'map-pin',
            self::OpeningStock          => 'archive',
            self::ContainerPackingList  => 'list',
            self::ActiveContainers      => 'cube',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::DepartmentsVws => 'Create departments and the virtual warehouses that belong to them.',
            self::Partners       => 'Import customers, suppliers, and other business partners.',
            self::ItemCategories => 'Set up the category tree before importing items.',
            self::Items          => 'Import the item master — SKUs, UoMs, batch and serial flags.',
            self::Locations      => 'Define warehouse aisles, bays, and bin locations.',
            self::OpeningStock          => 'Post opening balances to initialise stock on hand.',
            self::ContainerPackingList  => 'Import a packing list for an existing container.',
            self::ActiveContainers      => 'Import active containers (expected, arrived, or parked). Needs first: Partners, Locations.',
        };
    }

    public function requiresOpeningBalancePermission(): bool
    {
        return $this === self::OpeningStock;
    }

    public function restrictedToSystemAdmin(): bool
    {
        return in_array($this, [self::DepartmentsVws, self::OpeningStock], true);
    }

    // ContainerPackingList and ActiveContainers: not restricted to system admin,
    // not requiring opening balance permission — defaults above handle that correctly.
}
