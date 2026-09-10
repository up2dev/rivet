<?php
/**
 * BaseController class file
 *
 * PHP Version 8.1
 *
 * @category Controller
 * @package  Rivet\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Controllers;

use Rivet\Services\ResponseService;
use Rivet\Data\Repositories\CRUD;
use Rivet\Exceptions\ClassResolutionException;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * BaseController
 *
 * @category Controller
 * @package  Rivet\Http\Controllers
 * @license  https://opensource.org/licenses/MIT MIT License
 */
abstract class BaseController extends Controller
{
    /**
     * List the methods that are not auth filterd.
     *
     * @var array AUTH_UNLIMITED
     */
    static $AUTH_UNLIMITED = [];

    /**
     * The Controller response service.
     *
     * @var ResponseService $response
     */
    protected $response;

    /**
     * The Repository.
     *
     * @var CRUD $repo
     */
    protected $repo = null;

    /**
     * Set the repository based on the child.
     *
     * @param CRUD $repo The CRUD child
     *
     * @return void
     */
    public function __construct(?CRUD $repo = null)
    {
        if (is_null($repo)) {
            // The self-resolution guard used to live here as a
            // special case; it's now handled once, at the source, in
            // ns_search() itself (see src/helpers.php) - CRUD's own
            // Model resolution needed the exact same guard, so fixing
            // it there instead of duplicating this check in every
            // caller.
            $repo = ns_search(get_class($this), 'repository', [
                'Http' => 'Data'
            ]);
            $repo = is_null($repo)? null: new $repo();
        }

        $repo?->setAuthUnlimited(static::$AUTH_UNLIMITED);

        $this->repo = $repo;
    }

    /**
     * Get the repository, or fail clearly if the Controller has none.
     *
     * Before this modernization pass, a Controller extending
     * BaseController without a matching Repository (missing class, or
     * a name that doesn't fit the Http\Controllers -> Data\Repositories
     * naming convention) failed later and confusingly, on whichever
     * CRUD-delegating method (list/show/add/...) happened to be called
     * first - as a generic "call to a member function on null".
     *
     * @return CRUD
     *
     * @throws ClassResolutionException When no repository was resolved
     */
    protected function repo(): CRUD
    {
        if (is_null($this->repo)) {
            throw ClassResolutionException::forTarget(
                static::class, 'repository'
            );
        }

        return $this->repo;
    }

    /**
     * Method called by the /{root} URL in GET.
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function list(Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->all());

        return $this->response->format();
    }

    /**
     * Method called by the /{root}/{uid} URL in GET.
     *
     * @param int     $uid     The unique id of the desired Model
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function show(int $uid, Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->read($uid));

        return $this->response->format();
    }

    /**
     * Method called by the /{root} URL in POST.
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function add(Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->create($request->post()), 201);

        return $this->response->format();
    }

    /**
     * Method called by the /{root} URL in POST.
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function massAdd(Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->massCreate($request->post()), 201);

        return $this->response->format();
    }

    /**
     * Method called by the /{root}/{uid} URL in PUT
     *
     * @param int     $uid     The unique id of the Model we want to edit
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function edit(int $uid, Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->update($request->post(), $uid));

        return $this->response->format();
    }

    /**
     * Method called by the /{root} URL in PUT
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function massEdit(Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->massUpdate($request->post()));

        return $this->response->format();
    }

    /**
     * Method called by the /{root}/{uid} URL in DELETE.
     *
     * @param int     $uid     The unique id of the poor Model we are going to delete T.T
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function remove(int $uid, Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->delete($request->post(), $uid));

        return $this->response->format();
    }

    /**
     * Method called by the /{root} URL in DELETE.
     *
     * @param Request $request The injected Request
     *
     * @return JsonResponse
     */
    public function massRemove(Request $request): JsonResponse
    {
        $this->setResponse($this->repo()->massDelete($request->post()));

        return $this->response->format();
    }


    /**
     * Set the response.
     *
     * @param mixed $body   The data the response should return
     * @param int   $status The HTTP code of the response
     *
     * @return void
     */
    protected function setResponse(mixed $body, int $status = 200): void
    {
        if (is_bool($body)) {
            $body = ($body)? $this->repo->getModel(): null;
        }

        $this->response = new ResponseService(
            $body,
            (is_null($body)? 404: $status)
        );

        if (is_null($body)) {
            switch (debug_backtrace()[1]['function']) {
                case 'add':
                    $this->response->setMetaData(
                        'details', "{$this->getModelName()} creation failed"
                    );
                    break;

                default:
                    $this->response->setMetaData(
                        'details', "{$this->getModelName()} not found"
                    );
            }
        }
    }

    /**
     * Get the data type
     *
     * @return string
     */
    protected function getModelName(): string
    {
        return (!is_null($this->repo))?
            ucfirst(strtolower($this->repo->getModelClassName())):
            'Targeted data'
        ;
    }
}
