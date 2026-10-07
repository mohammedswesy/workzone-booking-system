<?php

namespace App\Enums;

/**
 * Unit type for a Workspace (bookable unit inside a Venue).
 * Venue = building; Workspace = unit.
 */
enum WorkspaceType: string
{
    case HotDesk = 'hot_desk';
    case PrivateOffice = 'private_office';
    case MeetingRoom = 'meeting_room';
    case TrainingRoom = 'training_room';
    case Other = 'other';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function fromBookingMode(BookingMode $mode): self
    {
        return $mode === BookingMode::Seat ? self::HotDesk : self::Other;
    }
}
