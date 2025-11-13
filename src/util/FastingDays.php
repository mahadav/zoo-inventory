<?php


namespace App\Util;

class FastingDays
{
    public const DAYS = [
        ['id' => -1, 'name' => 'None'],
        ['id' => 1, 'name' => 'Monday'],
        ['id' => 2, 'name' => 'Tuesday'],
        ['id' => 3, 'name' => 'Wednesday'],
        ['id' => 4, 'name' => 'Thursday'],
        ['id' => 5, 'name' => 'Friday'],
        ['id' => 6, 'name' => 'Saturday'],
        ['id' => 7, 'name' => 'Sunday'],
    ];

    public static function getNameById(int $id): ?string
    {
        foreach (self::DAYS as $day) {
            if ($day['id'] === $id) {
                return $day['name'];
            }
        }
        return null;
    }




}
