<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class AuthorsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('authors');
        $this->setPrimaryKey('id');
        $this->setEntityClass('App\Model\Entity\Author');

        $this->hasMany('Books', [
            'foreignKey' => 'author_id',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->requirePresence('name', 'create')
            ->notEmptyString('name')
            ->maxLength('name', 255);

        return $validator;
    }
}
