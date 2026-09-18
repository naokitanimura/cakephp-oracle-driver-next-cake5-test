<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $name
 */
class Author extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
