<?php
/**
 * DataValidate class file
 *
 * PHP Version 8.1
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Http\Middleware;

use Closure;
use Exception;
use Rivet\Services\ValidatorService;
use Rivet\Services\ResponseService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * DataValidate
 *
 * @category Middleware
 * @package  Rivet\Http\Middleware
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class DataValidate
{
    /**
     * The methods that should be tested
     * (allow the middleware to be set on named routes)
     *
     * @var array $_methods
     */
    private $_methods = [ 'POST', 'PUT', 'PATCH' ];

    /**
     * An instance of a child of ValidatorService that contains
     * the validation rules
     *
     * @var ValidatorService $_validator
     */
    private $_validator = null;

    /**
     * Handle an incoming request.
     *
     * @param Request $request   The request to validate
     * @param Closure $next      The controller method passed in routes
     * @param string  $validator The validator name
     * @param string  $namespace The validator namespace (default App)
     *
     * @return Response
     */
    public function handle(
        Request $request,
        Closure $next,
        string $validator,
        string $namespace = 'app'
    ): Response
    {
        if (in_array($request->getMethod(), $this->_methods)) {
            $this->_setValidator($request, nsval($validator), nsval($namespace));

            if (!$this->_validator->isValidated()) {
                return (new ResponseService(
                    $this->_validator->getErrors(), 400
                ))->format();
            }
        }

        return $next($request);
    }

    /**
     * Check if the Validator exist, if yes call it, if not throw an error.
     *
     * @param Request $request   The request to validate
     * @param string  $validator The validator name
     * @param string  $namespace The validator namespace
     *
     * @return void
     */
    private function _setValidator(
        Request $request, string $validator, string $namespace
    ): void
    {
        $validator = "{$namespace}\\Data\\Validators{$validator}Validator";

        if (!class_exists($validator)) {
            throw new Exception("{$validator} not found");
        }

        $this->_validator = new $validator(
            $request->all(),
            intval($request->route('uid'))
        );
    }
}
