<?php

namespace DTApi\Http\Controllers;

use App\Http\Requests\Booking\{Create as BookingCreate, Update as BookingUpdate};
use DTApi\Repository\BookingRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Exception;

/**
 * Class BookingController
 * @package DTApi\Http\Controllers
 */
class BookingController extends Controller
{
    /**
     * @var BookingRepository
     */
    protected $repository;

    /**
     * BookingController constructor.
     * @param BookingRepository $bookingRepository
     */
    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        $response  = null;
        $user_id   = $request->get('user_id');
        $role_id   = $request->__authenticatedUser->user_type;

        if ($user_id) {
            $response = $this->repository->getUsersJobs($user_id);
        } elseif (in_array($role_id, [config('app.admin_role_id'), config('app.superadmin_role_id')])) {
            $response = $this->repository->getAll($request);
        }

        return $this->__sendResponse($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $response = $this->repository->with('translatorJobRel.user')->find($id);
        return $this->__sendResponse($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function store(BookingCreate $request)
    {
        $code     = 200;
        $message  = 'Booking created successfully!';
        $response = array();
        try {
            $data     = $request->validated();
            $response = $this->repository->store($request->__authenticatedUser, $data);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
            Log::error($e);
        }

        return $this->__sendResponse($response, $code, $message);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function update($id, BookingUpdate $request)
    {
        $code     = 200;
        $message  = 'Booking created successfully!';
        $response = array();
        try {
            $data     = $request->validated();
            $data     = $data->except(['_token', 'submit']);

            $authUser = $request->__authenticatedUser;

            $response = $this->repository->updateJob($id, $data, $authUser);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
            Log::error($e);
        }

        return $this->__sendResponse($response, $code, $message);
    }
}
