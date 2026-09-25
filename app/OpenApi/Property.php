<?php

namespace App\OpenApi;

use OpenApi\Undefined;

/**
 * OA\Property с enum-значениями в example ($ref на Data-схемы swagger-php строит по типу свойства).
 *
 * Остальные аргументы пробрасываются по имени — порядок параметров родителя меняется
 * между версиями swagger-php, позиционная передача ломалась при обновлениях.
 */
#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_PROPERTY | \Attribute::TARGET_PARAMETER | \Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
class Property extends \OpenApi\Attributes\Property
{
    public function __construct(
        mixed $example = Undefined::UNDEFINED,
        mixed ...$attributes,
    ) {
        if ($example instanceof \UnitEnum) {
            $example = $example->value;
        }

        parent::__construct(...$attributes, example: $example);
    }
}
