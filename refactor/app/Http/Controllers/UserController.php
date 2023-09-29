<?php

namespace DTApi\Http\Controllers;

use App\Http\Requests\User\UserValidate;
use DTApi\Repository\UserRepository;
use Illuminate\Http\Request;

/**
 * Class UserController
 * @package DTApi\Http\Controllers
 */
class UserController extends Controller
{
    /**
     * @var UserRepository
     */
    protected $repository;

    /**
     * UserController constructor.
     * @param UserRepository $userRepository
     */
    public function __construct(UserRepository $userRepository)
    {
        $this->repository = $userRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(UserValidate $request)
    {
        $user_id  = $request->input('user_id');
        $response = $this->repository->getUsersJobsHistory($user_id, $request);
        return $this->__sendResponse($response);
    }
}
