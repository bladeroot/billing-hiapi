<?php
/**
 * API for Billing
 *
 * @link      https://github.com/hiqdev/billing-hiapi
 * @package   billing-hiapi
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2017, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\billing\hiapi\repositories;

class PriceCreationDto
{
    public $id;

    public $type;

    public $type_id;

    public $target_id;

    public $target_name;

    public $target_type_id;

    public $target_type_name;

    public $quantity;

    public $unit;

    public $currency;

    public $price;

    public static function fromArray(array $items)
    {
        $instance = new self();
        foreach ($items as $key => $value) {
            $instance->$key = $value;
        }

        return $instance;
    }
}
