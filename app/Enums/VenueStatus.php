<?php

namespace App\Enums;

/**
 * Venue lifecycle status (building-level). Same string values as WorkspaceStatus.
 * Venue = building; Workspace = unit.
 */
enum VenueStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
