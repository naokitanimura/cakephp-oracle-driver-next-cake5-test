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

        // "author" (VARCHAR2, existing CRUD/Query test data) is a separate,
        // unrelated column -- propertyName is set explicitly so the
        // associated Author entity hydrates into "author_ref" instead of
        // colliding with it.
        $this->belongsTo('Authors', [
            'foreignKey' => 'author_id',
            'propertyName' => 'author_ref',
        ]);
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
