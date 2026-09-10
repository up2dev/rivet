<?php
/**
 * ResponseService class file
 *
 * PHP Version 8.1
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use MongoDB\Laravel\Eloquent\Model as MongoModel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ResponseService
 *
 * @category Service
 * @package  Rivet\Services
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class ResponseService
{
    /**
     * The HTTP code.
     *
     * @var int
     */
    protected $status = 200;

    /**
     * The HTTP headers.
     *
     * @var int
     */
    protected $headers = [];

    /**
     * The response body.
     *
     * @var mixed
     */
    protected $body = null;

    /**
     * The paginator.
     *
     * @var Paginator
     */
    protected $paginator = null;

    /**
     * The response metadata.
     *
     * @var array
     */
    protected $metadata = [];

    /**
     * Set the response object.
     *
     * @param mixed $body   The data the response should return
     * @param int   $status The HTTP code of the response
     *
     * @return void
     */
    public function __construct(mixed $body, int $status = 200)
    {
        $this->status = $status;
        $is_success = ($this->status >= 200 && $this->status < 300);
        $this->metadata = [
            'success' => $is_success,
            'status'  => $this->status,
            'message' => trans("rivet::status.{$status}")
        ];
        $this->body = $body;

        if ($this->body instanceof Collection) {
            $this->metadata['count'] = $this->body->count();
        }

        if (!$is_success) {
            $this->body = [
                'error'  => (is_string($body)? $body: ''),
                'fields' => (is_array($body)? $body: [])
            ];
        }

        if (is_object($body) && get_class($body) === Paginator::class) {
            $this->body = $body->items();
            $this->body = (
                $this->body instanceof Collection?
                    $this->body: Collection::make($this->body)
            );

            $this->paginator = $body;

            $this->_paginatorMetadata();
        }
    }

    /**
     * Add or edit metadata info.
     *
     * @param int   $key   The key of the metadata
     * @param mixed $value The value of the metadata
     *
     * @return ResponseService
     */
    public function setMetaData(string $key, $value): ResponseService
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    /**
     * Add or edit metadata info.
     *
     * @param int    $key   The name of the header
     * @param string $value The value of the header
     *
     * @return ResponseService
     */
    public function setHeader(string $key, string $value): ResponseService
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Serialize and return a response in regard of the Accept header.
     *
     * CORS headers are deliberately NOT set here: Laravel's own
     * HandleCors middleware already covers 'api/*' (config('cors.php'))
     * and runs after this on the way out, unconditionally overwriting
     * anything set at this layer - setting them here was dead code
     * that happened to look like it worked. Configure CORS via
     * config/cors.php, the standard Laravel mechanism, instead.
     *
     * @param array $headers Optional response headers.
     *
     * @return mixed
     */
    public function format(array $headers = []): mixed
    {
        $accept = Request::header('Accept');
        $response = (
            new ResponseService('Accept header required', 400)
        )->_JSON();
        $queries = DB::getQueryLog();
        $queries_duration = 0;

        foreach ($queries as $query) {
            $queries_duration += $query['time'];
        }

        $this->headers = array_merge($this->headers, $headers);

        $this->metadata = array_merge($this->metadata, [
            'date'     => (
                new \DateTime('NOW', new \DateTimeZone('UTC'))
            )->format('Y-m-d H:i:s.u'),
            'duration' => microtime(true) - $_SERVER['REQUEST_TIME'],
            'sql_duration' => $queries_duration / 1000
        ]);

        if ($this->status < 200 || $this->status >= 300) {
            $accept = 'application/json';
        }

        switch ($accept) {
            case 'application/json':
                $response = $this->_JSON($this->headers);
                break;

            case 'application/octet-stream':
                $response = $this->_file($this->headers);
                break;
        }

        return $response;
    }

    /**
     * Serialize and return a JSON response.
     *
     * @param array $headers Optional response headers.
     *
     * @return JsonResponse
     */
    private function _JSON(array $headers = []): JsonResponse
    {
        return response()->json(
            [ 'meta' => $this->metadata, 'data' => $this->body ],
            $this->status,
            array_merge($headers, [
                'Content-Type' => 'application/json'
            ])
        );
    }

    /**
     * Return a binary file response.
     *
     * @param array $headers Optional response headers.
     *
     * @return BinaryFileResponse
     */
    private function _file(array $headers = []): BinaryFileResponse
    {
        return (new BinaryFileResponse($this->body, $this->status, [
            'Content-Type' => File::mimeType($this->body)
        ]));
    }

    /**
     * Set paginator metadata.
     *
     * @return void
     */
    private function _paginatorMetadata(): void
    {
        if (!is_null($this->paginator)) {
            $this->metadata['pagination'] = [
                'page'         => $this->paginator->currentPage(),
                'last'         => $this->paginator->lastPage(),
                'url'          => $this->paginator->url(
                    $this->paginator->currentPage()
                ),
                'first_url'    => $this->paginator->url(1),
                'last_url'     => $this->paginator->url(
                    $this->paginator->lastPage()
                ),
                'previous_url' => $this->paginator->previousPageUrl(),
                'next_url'     => $this->paginator->nextPageUrl(),
                'count_over'   => $this->paginator->total(),
                'count'        => $this->paginator->count(),
                'limit'        => $this->paginator->perPage()
            ];
        }
    }
}
