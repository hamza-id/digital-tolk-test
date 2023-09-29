<?php

namespace DTApi\Http\Controllers;

use App\Http\Requests\Distance\{Update as DistanceUpdate};
use DTApi\Repository\DistanceRepository;


/**
 * Class DistanceController
 * @package DTApi\Http\Controllers
 */
class DistanceController extends Controller
{
    /**
     * @var DistanceRepository
     */
    protected $repository;

    /**
     * DistanceController constructor.
     * @param DistanceRepository $distanceRepository
     */
    public function __construct(DistanceRepository $distanceRepository)
    {
        $this->repository = $distanceRepository;
    }

    public function distanceFeed(DistanceUpdate $request)
    {
        $code     = 200;
        $message  = 'Record updated successfully!';
        $response = array();
        try {
            $data = $request->validated();
            $response = $this->repository->distance($data);
        } catch (Exception $e) {
            $message = $e->getMessage();
            $code    = $e->getCode();
            Log::error($e);
        }

        return $this->__sendResponse($response, $code, $message);
    }
}
