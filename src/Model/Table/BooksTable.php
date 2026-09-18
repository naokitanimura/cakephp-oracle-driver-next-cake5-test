<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class BooksTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('books');
        $this->setPrimaryKey('id');
        $this->setEntityClass('App\Model\Entity\Book');

        $this->addBehavior('Timestamp');
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence(['title', 'author'], 'create')
            ->notEmptyString('title')
            ->notEmptyString('author')
            ->maxLength('title', 255)
            ->maxLength('author', 255)
            ->numeric('price');

        return $validator;
    }
}
