<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\BaseRepository;
/**
 * Class UserRepository
 * @package App\Repositories
 * @version July 10, 2018, 11:44 am UTC
 *
 * @method User findWithoutFail($id, $columns = ['*'])
 * @method User find($id, $columns = ['*'])
 * @method User first($columns = ['*'])
*/
class UserRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'id',
        'first_name',
        'last_name',
        'email',
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return User::class;
    }
}
