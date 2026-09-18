<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * @property int $id
 * @property string $title
 * @property string $author
 * @property int|null $author_id
 * @property float $price
 * @property \Cake\I18n\DateTime $created
 * @property \Cake\I18n\DateTime $modified
 * @property \App\Model\Entity\Author|null $author_ref
 */
class Book extends Entity
{
    protected array $_accessible = [
        '*' => true,
        'id' => false,
    ];
}
