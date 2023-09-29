<?php

namespace DTApi\Http\Controllers;

use App\Http\Requests\Job\JobValidate;
use DTApi\Repository\JobRepository;
use Illuminate\Http\Request;

/**
 * Class JobController
 * @package DTApi\Http\Controllers
 */
class JobController extends Controller
{
    /**
     * @var JobRepository
     */
    protected $repository;

    /**
     * JobController constructor.
     * @param JobRepository $jobRepository
     */
    public function __construct(JobRepository $jobRepository)
    {
        $this->repository = $jobRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(JobValidate $request)
    {
        $data      = $request->validated();
        $auth_user = $request->__authenticatedUser;
        $response  = $this->repository->acceptJob($data->job_id, $auth_user);
        return $this->__sendResponse($response);
    }

    public function acceptJobWithId(JobValidate $request)
    {
        $data      = $request->validated();
        $auth_user = $request->__authenticatedUser;

        $response  = $this->repository->acceptJobWithId($data->job_id, $auth_user);
        return $this->__sendResponse($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function cancelJob(JobValidate $request)
    {
        $data      = $request->validated();
        $auth_user = $request->__authenticatedUser;

        $response  = $this->repository->cancelJobAjax($data->job_id, $auth_user);
        return $this->__sendResponse($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function endJob(Request $request)
    {
        $data     = $request->validated();
        $response = $this->repository->endJob($data);

        return $this->__sendResponse($response);
    }

    public function customerNotCall(JobValidate $request)
    {
        $data     = $request->validated();
        $response = $this->repository->customerNotCall($data->job_id);

        return $this->__sendResponse($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getPotentialJobs(Request $request)
    {
        $user     = $request->__authenticatedUser;
        $response = $this->repository->getPotentialJobs($user);

        return $this->__sendResponse($response);
    }

    public function reopen(Request $request)
    {
        $data     = $request->all();
        $response = $this->repository->reopen($data);

        return $this->__sendResponse($response);
    }
}
