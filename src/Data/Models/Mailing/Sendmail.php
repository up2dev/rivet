<?php
/**
 * Sendmail class file
 *
 * PHP Version 8.1
 *
 * @category Model
 * @package  Rivet\Data\Models\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
namespace Rivet\Data\Models\Mailing;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Rivet\Data\Models\BaseModel;
use Rivet\Mail\BaseMail;

/**
 * A logged/queued email: created either synchronously by
 * MessageSendingListener as an audit trail of an email actually sent,
 * or explicitly with a null sent_at to be picked up later by
 * SendmailService/ProcessSendmail.
 *
 * @category Model
 * @package  Rivet\Data\Models\Mailing
 * @license  https://opensource.org/licenses/MIT MIT License
 */
class Sendmail extends BaseModel
{
    use HasFactory, SoftDeletes;

    /**
     * The uid associated with the model log.
     *
     * @var string
     */
    public $log_uid = 'Sendmail';

    /**
     * The attributes that are mass assignable. Matches the keys
     * MessageSendingListener::handle() writes.
     *
     * @var array
     */
    protected $fillable = [
        'token', 'from', 'to', 'subject', 'content', 'sent_at', 'is_success'
    ];

    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [ 'deleted_at' ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'sent_at'    => 'datetime',
        'is_ordered' => 'boolean'
    ];

    /**
     * -------------------------------------------------------------------------
     * Relations
     * -------------------------------------------------------------------------
     */

    /**
     * -------------------------------------------------------------------------
     * Mutators
     * -------------------------------------------------------------------------
     */

    /**
     * Set the sendmail's from.
     *
     * @param string|array $value The from value
     *
     * @return void
     */
    public function setFromAttribute(string|array $value): void
    {
        if (is_array($value)) {
            $temp_value = '';

            foreach ($value as $key => $address) {
                $temp_value .= ($key === 0)? '': ', ';

                if (is_array($address)) {
                    $temp_value .= "{$address['name']}: {$address['address']}";
                } else {
                    $temp_value .= "{$address->getName()}: {$address->getAddress()}";
                }
            }

            $value = $temp_value;
        }

        $this->attributes['from'] = $value;
    }

    /**
     * Get the sendmail's from.
     *
     * @param string $value The from value
     *
     * @return array
     */
    public function getFromAttribute(string $value): array
    {
        $array_value = [];

        // Str::after($subject, $search)/Str::before(): note the
        // argument order.
        foreach (explode(', ', $value) as $from) {
            $array_value[] = [
                'address' => Str::after($from, ': '),
                'name' => Str::before($from, ': ')
            ];
        }

        return $array_value[0];
    }

    /**
     * Set the sendmail's to.
     *
     * @param string|array $value The to value
     *
     * @return void
     */
    public function setToAttribute(string|array $value): void
    {
        if (is_array($value)) {
            $temp_value = '';

            foreach ($value as $key => $address) {
                $temp_value .= ($key === 0)? '': ', ';

                if (is_array($address)) {
                    $temp_value .= "{$address['name']}: {$address['address']}";
                } else {
                    $temp_value .= "{$address->getName()}: {$address->getAddress()}";
                }
            }

            $value = $temp_value;
        }

        $this->attributes['to'] = $value;
    }

    /**
     * Get the sendmail's to.
     *
     * @param string $value The to value
     *
     * @return array
     */
    public function getToAttribute(string $value): array
    {
        $array_value = [];

        foreach (explode(', ', $value) as $from) {
            $array_value[] = [
                'address' => Str::after($from, ': '),
                'name' => Str::before($from, ': ')
            ];
        }

        return $array_value;
    }

    /**
     * Set the sendmail's content.
     *
     * @param string $value The email value
     *
     * @return void
     */
    public function setContentAttribute(string|array $value): void
    {
        // Return here, or the array branch's result gets immediately
        // overwritten by e($value) below (which throws on an array).
        if (is_array($value)) {
            $this->attributes['content'] = "__json:" . json_encode(
                Sendmail::netralizeContent($value)
            );

            return;
        }

        $this->attributes['content'] = e($value);
    }

    /**
     * Get the sendmail's content.
     *
     * @param string $value The content value
     *
     * @return string|array
     */
    public function getContentAttribute(string $value): string|array
    {
        if (Str::startsWith($value, '__json:')) {
            // json_decode(..., true) - without it, this returns
            // stdClass, not the array retriveContent() requires.
            $value = Sendmail::retriveContent(
                json_decode(Str::after($value, '__json:'), true)
            );
        }

        return $value;
    }

    /**
     * Set the sendmail's content.
     *
     * @param string|null $value The email value
     *
     * @return void
     */
    public function setTokenAttribute(?string $value): void
    {
        $this->attributes['token'] = (
            is_null($value)? Sendmail::tokenize(): $value
        );
    }

    /**
     * Send an email if content = [ \
     * "template" => "...", \
     * "attributes" => [ ... ] \
     * ]
     *
     * @return bool
     */
    public function send(): bool
    {
        $attributes = $this->content['attributes'];

        $attributes['from_address'] = $this->from['address'];
        $attributes['from_name'] = $this->from['name'];
        // BaseMail expects ['email'=>.., 'name'=>..]; getToAttribute()
        // produces ['address'=>.., 'name'=>..].
        $attributes['to_addresses'] = array_map(
            fn ($recipient) => [
                'email' => $recipient['address'], 'name' => $recipient['name']
            ],
            $this->to
        );
        // Reuse this row's own token so MessageSendingListener updates
        // it instead of creating a duplicate log entry.
        $attributes['sendmail_token'] = $this->token;

        if (
            is_array($this->content) &&
            array_key_exists('template', $this->content) &&
            array_key_exists('attributes', $this->content)
        ) {
            Mail::send(new BaseMail(
                $this->content['template'], $attributes
            ));

            // Mail::send() throws on failure, so reaching this line
            // means it succeeded.
            $this->sent_at = now();
            $this->save();
        }

        return !is_null($this->sent_at);
    }

    /**
     * Netralize a data structure to store it. \
     * Remove objects that don't contain ids. \
     * If the object has an id it will be store to be retrived has a model.
     *
     * @return array
     */
    public static function netralizeContent(array $value): array
    {
        foreach ($value as $key => $val) {
            if (is_object($val)) {
                if (!is_null($val->id)) {
                    $value[$key] = get_class($val) . ":{$val->id}";
                } else {
                    unset($value[$key]);
                }
            } elseif (is_array($val)) {
                $value[$key] = Sendmail::netralizeContent($val);
            }
        }

        return $value;
    }

    /**
     * Retrive a data structure that has been netralized. \
     * Retrive the models based in the string \Namespace\Model:id.
     *
     * @return array
     */
    public static function retriveContent(array $value): array
    {
        foreach ($value as $key => $val) {
            if (is_string($val) && class_exists(Str::before($val, ':'))) {
                $class = Str::before($val, ':');
                $id = Str::after($val, ':');

                $value[$key] = $class::firstWhere('id', $id);
            } elseif (is_array($val)) {
                $value[$key] = Sendmail::retriveContent($val);
            }
        }

        return $value;
    }

    /**
     * Create a token.
     *
     * @return string
     */
    public static function tokenize(): string
    {
        do {
            $token = Str::random(32);
        } while (!is_null(Sendmail::firstWhere('token', $token)));

        return $token;
    }
}
