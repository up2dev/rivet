<?php
/**
 * Validator class file
 *
 * PHP Version 8.1
 *
 * @category Validator
 * @package  Rivet\Data\Validators
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Validators;

use Illuminate\Support\Facades\Request;
use Rivet\Services\ValidatorService;

/**
 * Validator
 *
 * @category Validator
 * @package  Rivet\Data\Validators
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Validator extends ValidatorService
{
    /**
     * The set of rules of the Validator.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * The set of rules of the Validator for resource edition.
     *
     * @var array
     */
    protected $edit_rules = [];

    /**
     * Build a validation.
     *
     * @param array  $fields The fields to validate
     * @param int    $uid    The unique ID of the model (is PUT/PATCH)
     */
    public function __construct(
        array $fields, ?int $uid = null
    ) {
        $this->_setRules($uid);
        $this->_processValues($uid);

        parent::__construct($fields);
    }

    /**
     * Set the rules array.
     *
     * @param int $uid The unique ID of the model (is PUT/PATCH)
     *
     * @return void
     */
    private function _setRules(?int $uid = null): void
    {
        if (
            in_array(Request::getMethod(), [ 'PUT', 'PATCH' ]) &&
            !empty($this->edit_rules)
        ) {
            $this->rules = $this->edit_rules;
        }
    }

    /**
     * Set variable values in rules array.
     *
     * @param int $uid The unique ID of the model (is PUT/PATCH)
     *
     * @return void
     */
    private function _processValues(?int $uid = null): void
    {
        $uid = $uid?: 'NULL';
        $user_id = auth()->user()? auth()->user()->id: 'NULL';

        foreach ($this->rules as $key => $rule) {
            if (is_string($rule)) {
                $this->rules[$key] = preg_replace(
                    ($uid === 'NULL'? '/\"?\:ID\:\"?/': '/\:ID\:/'), $uid, $rule
                );
            } elseif (is_array($rule)) {
                foreach ($rule as $k => $r) {
                    if (is_string($r)) {
                        $this->rules[$key][$k] = preg_replace(
                            ($uid === 'NULL'? '/\"?\:ID\:\"?/': '/\:ID\:/'), $uid, $r
                        );
                    }
                }
            }
        }

        foreach ($this->rules as $key => $rule) {
            if (is_string($rule)) {
                $this->rules[$key] = preg_replace((
                    $user_id === 'NULL'? '/\"?\:AUTH_ID\:\"?/': '/\:AUTH_ID\:/'
                ), $user_id, $rule);
            } elseif (is_array($rule)) {
                foreach ($rule as $k => $r) {
                    if (is_string($r)) {
                        $this->rules[$key][$k] = preg_replace((
                            $user_id === 'NULL'? '/\"?\:AUTH_ID\:\"?/': '/\:AUTH_ID\:/'
                        ), $user_id, $r);
                    }
                }
            }
        }
    }
}
