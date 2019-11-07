<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 *(at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @version 0.1.3
 * @author Ruslan V. Uss
 *
 * homepage: https://github.com/UncleRus/php-gnupg
 */

namespace App\Model;

class GpgError extends \Exception
{
    /**
     * STDERR output of GnuPG executable
     * @var string
     */
    public $err;

    public function __construct($message, $err)
    {
        parent::__construct($message);
        $this->err = $err;
    }
}

class GpgGeneralError extends GpgError
{
    public function __construct($err)
    {
        $data = array();
        foreach (explode("\n", $err) as $line)
            if (substr($line, 0, 4) == 'gpg:')
                $data[] = $line;
        parent::__construct(implode("\n", $data), $err);
    }
}

class GpgProcError extends GpgError
{
    public function __construct($binary)
    {
        parent::__construct('Cannot execute GnuPG binary('. $binary  . ')', null);
    }
}

class GpgUnknownStatus extends GpgError
{
    public function __construct($status)
    {
        parent::__construct('Unknown GnuPG status: ' . $status, null);
    }
}

class GpgSmartcardError extends GpgError
{
    static private $reasons = array(
        'Unspecified error',
        'Cancelled',
        'Bad PIN'
    );

    public function __construct($code, $err)
    {
        parent::__construct(isset(self::$reasons[$code]) ? self::$reasons[$code] : 'Unknown error', $err);
    }
}

class GpgNoDataError extends GpgError
{
    static private $reasons = array(
        1 => 'No armored data',
        2 => 'Expected a packet but did not found one',
        3 => 'Invalid packet found, this may indicate a non OpenPGP message',
        4 => 'Signature expected but not found'
    );

    public function __construct($err, $code)
    {
        parent::__construct(isset(self::$reasons[$code]) ? self::$reasons[$code] : 'No valid data found', $err);
    }
}


class GpgUnexpectedData extends GpgError
{
    static private $reasons = array(
        'Not further specified',
        'Corrupted message structure'
    );

    public function __construct($err, $code)
    {
        parent::__construct(isset(self::$reasons[$code]) ? self::$reasons[$code] : 'Unexpected data found', $err);
    }
}

class GpgKeyDeleteError extends GpgError
{
    static private $reasons = array(
        2 => 'Must delete secret key first',
        3 => 'Ambigious specification'
    );

    public function __construct($code, $err)
    {
        parent::__construct(isset(self::$reasons[$code]) ? self::$reasons[$code] : 'Unknown error', $err);
    }
}

class GpgPassphraseError extends GpgError {}

class GpgAlgorithmError extends GpgError {}

class GpgKeyError extends GpgError {}

class GpgDecryptionError extends GpgError {}

class GpgInvalidMemberError extends GpgError
{
    static private $reasons = array(
        'No specific reason given',
        'Not found',
        'Ambigious specification',
        'Wrong key usage',
        'Key revoked',
        'Key expired',
        'No CRL known',
        'CRL too old',
        'Policy mismatch',
        'Not a secret key',
        'Key not trusted',
        'Missing certificate',
        'Missing issuer certificate'
    );

    public function __construct($err, $message, $reason = null, $who = null)
    {
        if (!is_null($reason))
            $reason = self::$reasons[$reason];
        parent::__construct(sprintf($message, $reason, $who), $err);
    }
}


abstract class GpgUtils
{
    static public function multiSplit($str)
    {
        return preg_split('/\s+/', $str);
    }

    static public function getTimestamp($value)
    {
        return strpos($value, 'T') === false
            ? (int) $value
            : \DateTime::createFromFormat('Ymd\THis', $value)->getTimestamp();
    }
}

/**
 * Base class for result of GPG operation
 */
abstract class GpgResult
{
    /**
     * GnuPG output
     * @var string
     */
    public $data;
    public $status;
    public $err;

    protected $processors = array(
        'IMPORT_OK' => false, 'NEWSIG' => false, 'KEY_CONSIDERED' => false, 'PINENTRY_LAUNCHED' => false,
        'ERROR' => '_error',
        'NODATA' => '_nodata',
        'UNEXPECTED' => '_unexpected',
        'SC_OP_FAILURE' => '_sc_op_failure',
        'INV_SGNR' => '_inv_member',
        'INV_RECP' => '_inv_member',
        'NO_SGNR' => '_no_member',
        'NO_RECP' => '_no_member',
        'NO_SECKEY' => '_no_seckey',
        'NO_PUBKEY' => '_no_pubkey',
        'MISSING_PASSPHRASE' => '_missing_passphrase',
        'BAD_PASSPHRASE' => '_bad_passphrase',
        'DECRYPTION_FAILED' => '_decryption_failed',
        'DECRYPTION_KEY' => false,
        'DECRYPTION_COMPLIANCE_MODE' => false,
        'VERIFICATION_COMPLIANCE_MODE' => false,
        'ENCRYPTION_COMPLIANCE_MODE' => false,
        'PROGRESS' => false,
    );

    public function handle()
    {
        if (is_null($this->status)) return;
        foreach ($this->status as $status)
        {
            $code = $status[0];
            //echo 'GpgResult::handle(): ' . $code . ": $status[1]\n";
            if (!isset($this->processors[$code]))
                throw new GpgUnknownStatus($code);
            if ($this->processors[$code])
                $this->{$this->processors[$code]}($code, $status[1]);
        }
    }

    protected function _error($code, $value)
    {
        throw new GpgGeneralError($this->err);
    }

    protected function _nodata($code, $value)
    {
        throw new GpgNoDataError($this->err, $value);
    }

    protected function _unexpected($code, $value)
    {
        throw new GpgUnexpectedData($this->err, $value);
    }

    protected function _sc_op_failure($code, $value)
    {
        throw new GpgSmartcardError($code, $this->err);
    }

    protected function _inv_member($code, $value)
    {
        list($reason, $who) = GpgUtils::multiSplit($value);
        throw new GpgInvalidMemberError(
            $this->err,
            $code == 'INV_SGNR'
                ? 'Invalid signer: %s(%s)'
                : 'Invalid recipient: %s(%s)',
            $reason,
            $who
        );
    }

    protected function _no_member($code, $value)
    {
        throw new GpgInvalidMemberError($this->err, code == 'INV_SGNR' ? 'No signers are usable' : 'No recipients are usable');
    }

    protected function _no_seckey($code, $value)
    {
        foreach ($this->status as $status) {
            $code = $status[0];

            if ($code == 'PLAINTEXT')
                return;
        }
        throw new GpgKeyError('The secret key(' . $value . ') is not available', $this->err);
    }

    protected function _no_pubkey($code, $value)
    {
        throw new GpgKeyError('The public key(' . $value . ') is not available', $this->err);
    }

    protected function _missing_passphrase($code, $value)
    {
        throw new GpgPassphraseError('Missing passphrase', $this->err);
    }

    protected function _bad_passphrase($code, $value)
    {
        throw new GpgPassphraseError('Bad passphrase for key ' . $value, $this->err);
    }

    protected function _decryption_failed($code, $value)
    {
        throw new GpgDecryptionError('The symmetric decryption failed', $this->err);
    }
}

/**
 * Import keys result.
 */
class GpgImportResult extends GpgResult
{
    static private $_counts = array(
        'count', 'noUserId', 'imported', 'importedRsa', 'unchanged',
        'nUids', 'nSubk', 'nSigs', 'nRevoked', 'secRead', 'secImported',
        'secDups', 'notImported'
    );

    static private $_ok_reasons = array(
        1 => 'Entirely new key',
        2 => 'New user IDs',
        4 => 'New signatures',
        8 => 'New subkeys',
        16 => 'Contains private key'
    );

    static private $_problem_reasons = array(
        1 => 'Invalid certificate',
        2 => 'Issuer certificate missing',
        3 => 'Certificate chain too long',
        4 => 'Error storing certificate'
    );

    /**
     * Array of import results.
     * @var array
     */
    public $results = array();

    /**
     * Summary counters. All of them also available as object properties.
     * @var array
     */
    public $counts = array();

    public function __construct()
    {
        $this->processors = array_merge(
            $this->processors,
            array(
                'IMPORTED' => false,
                'KEYEXPIRED' => false,
                'SIGEXPIRED' => false,
                'IMPORT_OK' => '_import_ok',
                'IMPORT_PROBLEM' => '_import_problem',
                'IMPORT_RES' => '_import_res'
            )
        );
    }

    public function handle()
    {
        parent::handle();
        if (empty($this->results) && $this->err)
            throw new GpgGeneralError($this->err);
    }

    private function _add_result($fingerprint, $reason, $problem = null)
    {
        $reson =(int) $reason;
        $problem = $problem ? (int) $problem : null;
        if ($fingerprint)
        {
            if ($reason == 0)
                $result_text = array('No actually changed');
            else
            {
                $result_text = array();
                foreach (self::$_ok_reasons as $bit => $text)
                    if ($bit & $reason)
                        $result_text[] = $text;
            }
        }
        $this->results[] = array(
            'fingerprint' => $fingerprint,
            'imported' => $reason > 0,
            'result' => $reason,
            'result_text' => $result_text,
            'problem' => $problem,
            'problem_text' => isset(self::$_problem_reasons[$problem]) ? self::$_problem_reasons[$problem] : null
        );
    }

    protected function _import_ok($code, $value)
    {
        list($reason, $fingerprint) = GpgUtils::multiSplit($value);
        $this->_add_result($fingerprint, $reason, null);
    }

    protected function _import_problem($code, $value)
    {
        $values = GpgUtils::multiSplit($value);
        if (count($values) > 1)
        {
            $problem = $values[0];
            $fingerprint = $values[1];
        }
        else
        {
            $problem = $values[0];
            $fingerprint = null;
        }
        $this->_add_result($fingerprint, -1, $problem);
    }

    protected function _import_res($code, $value)
    {
        $result = GpgUtils::multiSplit($value);
        foreach (self::$_counts as $i => $count)
            $this->counts[$count] =(int) $result[$i];
    }

    public function __get($attr)
    {
        if (!in_array($attr, self::$_counts))
            throw new \Exception('Unknown property ' . $attr);
        return isset($this->counts[$attr]) ? $this->counts[$attr] : 0;
    }
}

/**
 * genKey() result.
 */
class GpgGenKeyResult extends GpgResult
{
    /**
     * Key type.
     * @var string
     */
    public $type;

    /**
     * Key fingerprint.
     * @var string
     */
    public $fingerprint;

    public function __construct()
    {
        $this->processors = array_merge(
            $this->processors,
            array(
                'PROGRESS' => false, 'GOOD_PASSPHRASE' => false, 'NODATA' => false,
                'KEY_NOT_CREATED' => '_key_not_created',
                'KEY_CREATED' => '_key_created'
            )
        );
    }

    protected function _key_not_created($code, $value)
    {
        throw new GpgGeneralError($this->err);
    }

    protected function _key_created($code, $value)
    {
        list($this->type, $this->fingerprint) = array_slice(GpgUtils::multiSplit($value), 0, 2);
    }
}

/**
 * listKeys() result
 * result contains in $keys property
 */
class GpgListKeysResult extends GpgResult
{
    static private $keywords = array(
        'pub' => 1, 'uid' => 1, 'sec' => 1, 'fpr' => 1, 'sub' => 1
    );
    static private $fields = array(
        'trust', 'length', 'algo', 'keyid', 'date', 'expires', 'dummy', 'ownertrust', 'uid'
    );
    static private $intFields = array(
        'length' => 1, 'algo' => 1, 'date' => 1, 'expires' => 1
    );

    /**
     * Keys data
     * @var array
     */
    public $keys = array();

    private $current = array();

    public function handle()
    {
        if (!$this->data && $this->err)
            throw new GpgGeneralError($this->err);
        $sub = false;
        foreach (explode("\n", $this->data) as $line)
        {
            $line = trim($line);
            if (!$line) continue;
            $fields = explode(':', $line);
            if (!isset(self::$keywords[$fields[0]]))
                continue;
            $value = array_slice($fields, 1);
            switch($fields[0])
            {
                case 'pub':
                case 'sec':
                    $sub = false;
                    if (!empty($this->current))
                        $this->keys[$this->current['fingerprint']] = $this->current;
                    $this->current = array();
                    foreach (self::$fields as $i => $field)
                        $this->current[$field] = isset(self::$intFields[$field]) ? (int) $value[$i] : $value[$i];
                    $this->current['uid'] = $this->current['uid'] != '' ? array($this->current['uid']) : array();
                    $this->current['subkeys'] = array();
                    unset($this->current['dummy']);
                    break;
                case 'uid':
                    $this->current['uid'][] = $value[8];
                    break;
                case 'fpr':
                    // FIXME: Full fingerprint processing for subkeys
                    if (!$sub)
                        $this->current['fingerprint'] = $value[8];
                    break;
                case 'sub':
                    $sub = true;
                    $this->current['subkeys'][] = array($value[3], $value[10]);
                    break;
            }
        }
        if (!empty($this->current))
            $this->keys[$this->current['fingerprint']] = $this->current;
    }
}


/**
 * exportKeys() result
 * field $data contains exported keys
 */
class GpgExportResult extends GpgResult
{
    public function handle() {}
}

/**
 * deleteKeys() result.
 */
class GpgDeleteResult extends GpgResult
{
    public function __construct()
    {
        $this->processors = array_merge(
            $this->processors,
            array('DELETE_PROBLEM' => '_delete_problem')
        );
    }

    protected function _delete_problem($code, $value)
    {
        $value =(int) $value;
        if ($value != 1)
            throw new GpgKeyDeleteError($value, $this->err);
    }
}

/**
 * sign() result.
 * Actual sign contains in property $data.
 */
class GpgSignResult extends GpgResult
{
    const TYPE_DETACHED = 'D';
    const TYPE_CLEARTEXT = 'C';
    const TYPE_STANDARD = 'S';

    /**
     * Sign
     * @var string
     */
    public $data;

    /**
     * Sign type
     * @var string
     */
    public $type;

    /**
     * Sign algorithm
     * @var int
     */
    public $algorithm;

    /**
     * Hash algorithm
     * @var int
     */
    public $hashAlgorithm;

    /**
     * Key fingerprint
     * @var string
     */
    public $fingerprint;

    /**
     * UTC timestamp of the sign
     * @var int
     */
    public $timestamp;

    /**
     * Signer data
     * @var string
     */
    public $signer;

    public function __construct()
    {
        $this->processors = array_merge(
            $this->processors,
            array(
                'NEED_PASSPHRASE' => false, 'GOOD_PASSPHRASE' => false,
                'BEGIN_SIGNING' => false, 'CARDCTRL' => false, 'KEYEXPIRED' => false,
                'SIGEXPIRED' => false, 'KEYREVOKED' => false, 'SC_OP_SUCCESS' => false,
                'USERID_HINT' => '_userid_hint',
                'SIG_CREATED' => '_sig_created',
            )
        );
    }

    protected function _userid_hint($code, $value)
    {
        $res = explode(' ', $value, 2);
        $this->signer = $res[1];
    }

    protected function _sig_created($code, $value)
    {
        list($this->type, $this->algorithm, $this->hashAlgorithm, $cls, $this->timestamp, $this->fingerprint) = GpgUtils::multiSplit($value);
        $this->algorithm =(int) $this->algorithm;
        $this->hashAlgorithm =(int) $this->hashAlgorithm;
        $this->timestamp = GpgUtils::getTimestamp($this->timestamp);
    }
}

/**
 * verify() result
 */
class GpgVerifyResult extends GpgResult
{
    const STATE_OK = 'OK';
    const STATE_SIG_EXPIRED = 'EXPSIG';
    const STATE_KEY_REVOKED = 'REVKEYSIG';
    const STATE_KEY_EXPIRED = 'EXPKEYSIG';

    /**
     * True if verified correctly
     * @var bool
     */
    public $valid = false;

    /**
     * Signer fingerprint
     * @var string
     */
    public $fingerprint;

    /**
     * Signature timestamp
     * @var int
     */
    public $timestamp;

    /**
     * Signature expiration timestamp
     * @var int
     */
    public $expireTimestamp;

    /**
     * Signature ID
     * @var string
     */
    public $id;

    /**
     * Signer key ID
     * @var string
     */
    public $keyId;

    /**
     * Signer name
     * @var string
     */
    public $signer;

    /**
     * Sign state
     * @var string
     */
    public $state = self::STATE_OK;

    public function __construct()
    {
        $this->processors = array_merge(
            $this->processors,
            array(
                'RSA_OR_IDEA' => false, 'IMPORT_RES' => false, 'PLAINTEXT' => false,
                'PLAINTEXT_LENGTH' => false, 'POLICY_URL' => false, 'DECRYPTION_INFO' => false,
                'DECRYPTION_OKAY' => false, 'FILE_START' => false, 'FILE_ERROR' => false,
                'FILE_DONE' => false, 'PKA_TRUST_GOOD' => false, 'PKA_TRUST_BAD' => false,
                'BADMDC' => false, 'GOODMDC' => false, 'TRUST_UNDEFINED' => false,
                'TRUST_NEVER' => false, 'TRUST_MARGINAL' => false, 'TRUST_FULLY' => false,
                'TRUST_ULTIMATE' => false, 'KEYEXPIRED' => false, 'SIGEXPIRED' => false,
                'KEYREVOKED' => false,

                'EXPSIG' => '_set_state',
                'EXPKEYSIG' => '_set_state',
                'REVKEYSIG' => '_set_state',
                'BADSIG' => '_badsig',
                'GOODSIG' => '_goodsig',
                'VALIDSIG' => '_validsig',
                'ERRSIG' => '_errsig',
                'SIG_ID' => '_sig_id'
            )
        );
    }

    protected function _set_state($code, $value)
    {
        $this->valid = false;
        $this->state = $code;
        list($this->keyId, $this->signer) = explode(' ', $value, 2);
    }

    protected function _badsig($code, $value)
    {
        $this->valid = false;
        list($this->keyId, $this->signer) = explode(' ', $value, 2);
    }

    protected function _goodsig($code, $value)
    {
        $this->valid = true;
        list($this->keyId, $this->signer) = explode(' ', $value, 2);
    }

    protected function _validsig($code, $value)
    {
        list($this->fingerprint, $_dummy,
            $this->timestamp, $this->expireTimestamp) = array_slice(GpgUtils::multiSplit($value), 0, 4);
        $this->timestamp = GpgUtils::getTimestamp($this->timestamp);
    }

    protected function _errsig($code, $value)
    {
        $this->valid = false;
        $raw = GpgUtils::multiSplit($value);
        if ($raw[5] == 4)
            throw new GpgAlgorithmError('Unsupported algorithm', $this->err);
        elseif ($raw[5] == 9)
            throw new GpgKeyError('Missing public key ' . $raw[0], $this->err);
        throw new GpgGeneralError($this->err);
    }

    protected function _sig_id($code, $value)
    {
        $raw = GpgUtils::multiSplit($value);
        $this->id = $raw[0];
    }
}


class GpgEncryptResult extends GpgVerifyResult
{
    /**
     * @var bool
     */
    public $signatureExpired = false;

    /**
     * @var bool
     */
    public $keyExpired = false;

    public function __construct()
    {
        parent::__construct();
        $this->processors = array_merge(
            $this->processors,
            array(
                'SC_OP_SUCCESS' => false, 'CARDCTRL' => false, 'ENC_TO' => false,
                'ERROR' => false, 'USERID_HINT' => false, 'BEGIN_SIGNING' => false,
                'NEED_PASSPHRASE' => false, 'NEED_PASSPHRASE_SYM' => false, 'GOOD_PASSPHRASE' => false,
                'BEGIN_DECRYPTION' => false, 'END_DECRYPTION' => false, 'DECRYPTION_OKAY' => false,
                'BEGIN_ENCRYPTION' => false, 'END_ENCRYPTION' => false,
                'SIG_CREATED' => false,

                'KEY_NOT_CREATED' => '_key_not_created',
                'KEYEXPIRED' => '_key_expired',
                'SIGEXPIRED' => '_sig_expired',
                'USERID_HINT' => '_userid_hint'
            )
        );
    }

    public function handle()
    {
        parent::handle();
        $this->valid = !empty($this->data);
    }

    protected function _key_not_created($code, $value)
    {
        throw new GpgGeneralError($this->err);
    }

    protected function _key_expired($code, $value)
    {
        $this->keyExpired = true;
    }

    protected function _sig_expired($code, $value)
    {
        $this->signatureExpired = true;
    }

    protected function _userid_hint($code, $value)
    {
        $res = explode(' ', $value, 2);
        $this->signer = $res[1];
    }
}

class GpgVersionResult
{
    public $data;
    public $status;
    public $err;

    public $version;

    public function handle()
    {
        preg_match('/gpg\s+\(GnuPG\)\s+(\d+)\./', $this->data, $g);
        if (!isset($g[1])) throw new GpgGeneralError('gpg: Could not get version');
        $this->version = (int)$g[1];
    }
}

/**
 * Encapsulate access to the gpg executable.
 */
class GnuPG
{
    /**
     * Full pathname for GPG binary.
     * @var string
     */
    public $binary;

    /**
     * Full pathname to where we can find the public and private keyrings.
     * @var string
     */
    public $homedir;

    /**
     * GnuPG major version
     * @var int
     */
    public $version;

    /**
     * Initialize a GPG process wrapper
     * @param string $binary Full pathname for GPG binary.
     * @param string $homedir Full pathname to where we can find the public and
     * 		private keyrings. Default is whatever gpg defaults to.
     */
    public function __construct($homedir = null, $binary = 'gpg')
    {
        $this->binary = $binary;
        $this->homedir = $homedir;
        $this->version = null;
    }

    protected function execute($result, $args, $stdin = null, $passphrase = false)
    {
        if (!$this->version && !in_array('--version', $args))
            $this->version = $this->execute(new GpgVersionResult(), array('--version'))->version;

        $cmd = array('--status-fd', '3', '--no-tty', '--lock-multiple', '--no-permission-warning');
        if ($this->homedir)
            $cmd = array_merge($cmd, array('--homedir', $this->homedir));
        if ($passphrase !== false)
        {
            if (!in_array('--batch', $args))
                $cmd[] = '--batch';
            $cmd = array_merge($cmd, array('--passphrase-fd', '4'));
            if ($this->version > 1)
                $cmd = array_merge($cmd, array('--pinentry-mode', 'loopback'));
        }
        $cmd = array_merge($cmd, $args);
        foreach ($cmd as &$arg)
            $arg = escapeshellarg($arg);
        $cmd = implode(' ', $cmd);

        //echo ">>> " . escapeshellcmd($this->binary) . ' ' . $cmd . "\n";
        $stdinHandle = fopen('data://text/plain;base64,'. base64_encode($stdin), 'r');

        $process = proc_open(
            escapeshellcmd($this->binary) . ' ' . $cmd,
            array(
                $stdinHandle,
                array('pipe', 'w'),
                array('pipe', 'w'),
                array('pipe', 'w'),
                array('pipe', 'r'),
            ),
            $pipes
        );

        if (!is_resource($process))
            throw new GpgProcError($this->binary);

        if ($passphrase !== false)
        {
            fwrite($pipes[4], $passphrase . "\n");
        }

        $result->data = stream_get_contents($pipes[1]);
        $result->err = stream_get_contents($pipes[2]);

        while(!feof($pipes[3]))
        {
            $line = stream_get_line($pipes[3], 1024, "\n");
            //echo "<<< " . $line . "\n";
            if (substr($line, 0, 8) != '[GNUPG:]') continue;
            $l = explode(' ', substr($line, 9), 2);
            $result->status[] = array($l[0], count($l) > 1 ? $l[1] : '');
        }
        fclose($pipes[1]);
        fclose($pipes[2]);
        fclose($pipes[3]);
        proc_close($process);
        fclose($stdinHandle);

        $result->handle();

        return $result;
    }

    /**
     * Import/merge keys. This adds the given keys to the keyring.
     * @param string $keyData Keys data
     * @return GpgImportResult
     */
    public function importKeys($keyData)
    {
        return $this->execute(new GpgImportResult(), array('--import'), $keyData);
    }

    /**
     * Import the keys with the given key IDs from a HKP keyserver.
     * @param string $keyserver Keyserver name
     * @param mixed $keys Single key ID string or array of multiple IDs
     * @return GpgImportResult
     */
    public function recvKeys($keyserver, $keys)
    {
        if (!is_array($keys))
            $keys = array($keys);
        return $this->execute(
            new GpgImportResult(),
            array_merge(array('--keyserver', $keyserver, '--recv-keys'), $keys)
        );
    }

    /**
     * List keys from the public or secret keyrings.
     * @param bool $secret List secret keys when true
     * @return GpgListKeysResult
     */
    public function listKeys($secret = false)
    {
        return $this->execute(
            new GpgListKeysResult(),
            array(
                '--list-' .($secret ? 'secret-keys' : 'keys'),
                '--fixed-list-mode',
                '--fingerprint',
                '--with-colons'
            )
        );
    }

    /**
     * Export keys
     * @param mixed $keys Single key ID string or array of multiple IDs
     * @param string $secret Export secret keys if true
     * @param string $passphrase key password, used when secret = true
     * @param bool $binary Armored format if false
     * @return GpgExportResult
     */
    public function exportKeys($keys, $secret = false, $passphrase = false, $binary = false)
    {
        $args = $binary ? array() : array('--armor');
        $args = array_merge($args, $secret ? array('--batch', '--export-secret-keys') : array('--export'));
        if (!is_array($keys))
            $keys = array($keys);
        return $this->execute(
            new GpgExportResult(),
            array_merge($args, $keys),
            $passphrase
        );
    }

    /**
     * Remove keys from the public or secret keyrings.
     * @param mixed $fingerprints Single key fingerprint string or array of multiple fingerprints
     * @param bool $secret Delete secret keys when true
     * @return GpgDeleteResult
     */
    public function deleteKeys($fingerprints, $secret = false)
    {
        if (!is_array($fingerprints))
            $fingerprints = array($fingerprints);
        return $this->execute(
            new GpgDeleteResult(),
            array_merge(
                array('--batch', '--yes', '--delete-' . ($secret ? 'secret-key' : 'key')),
                $fingerprints
            )
        );
    }

    /**
     * Check is given key exists
     * @param string $key Key ID
     * @param bool $secret Check secret key if true
     * @return bool True if key exists
     */
    public function keyExists($key, $secret = false)
    {
        if (strlen($key) < 8)
            return false;
        $key = strtoupper($key);
        $res = $this->listKeys($secret, $key);
        foreach ($res->keys as $fingerprint => $data)
            if (substr($fingerprint, -strlen($key)) == $key)
                return true;
        return false;
    }

    /**
     * Generate --gen-key input per gpg doc/DETAILS
     * @param array $args Associative array of key parameters
     * @return string
     */
    public function genKeyInput($args = array())
    {
        $login = getenv('LOGNAME');
        if (!$login)
            $login = getenv('USERNAME');
        if (!$login)
            $login = 'user';
        $hostname = gethostname();
        if (!$hostname)
            $hostname = 'localhost';
        $type = isset($args['Key-Type']) ? $args['Key-Type'] : 'RSA';
        $params = $args + array(
                'Key-Length' => 1024,
                'Name-Real' => 'Autogenerated Key',
                'Name-Comment' => 'Generated by php-gnupg',
                'Name-Email' => $login . '@' . $hostname
            );
        $out = 'Key-Type: ' . $type . PHP_EOL;
        foreach ($params as $param => $value)
            $out .= $param . ': ' . $value . PHP_EOL;
        return $out . '%commit' . PHP_EOL;
    }

    /**
     * Generate a new key pair; you might use genKeyInput() to create the control input.
     * @param string $input GnuPG key generation control input
     * @return GpgGenKeyResult
     */
    public function genKey($input)
    {
        return $this->execute(new GpgGenKeyResult(), array('--gen-key', '--batch'), $input);
    }

    /**
     * Make a signature.
     * @param string $message Message for sign.
     * @param string $keyId key for signing, default will be used if null
     * @param string $passphrase key password
     * @param bool $clearsign Make a clear text signature.
     * @param bool $detach Make a detached signature.
     * @param bool $binary If false, create ASCII armored output.
     * @return GpgSignResult
     */
    public function sign($message, $keyId = null, $passphrase = null,
                         $clearsign = true, $detach = false, $binary = false)
    {
        $args = array($binary ? '-s' : '-sa');
        if ($detach)
            $args[] = '--detach-sign';
        elseif ($clearsign)
            $args[] = '--clearsign';
        if ($keyId)
            $args = array_merge($args, array('--local-user', $keyId));
        return $this->execute(new GpgSignResult(), $args, $message, $passphrase);
    }

    /**
     * Make a signature.
     * Warning: Entire file will be loaded into memory.
     * @param string $filename File for sign.
     * @param string $keyId key for signing, default will be used if null
     * @param string $passphrase key password
     * @param bool $clearsign Make a clear text signature.
     * @param bool $detach Make a detached signature.
     * @param bool $binary If false, create ASCII armored output.
     * @return GpgSignResult
     */
    public function signFile($filename, $keyId = null, $passphrase = null,
                             $clearsign = true, $detach = false, $binary = false)
    {
        return $this->sign(
            file_get_contents($filename),
            $keyId, $passphrase, $clearsign, $detach, $binary
        );
    }

    /**
     * Verify given signature
     * @param string $sign Signature to verify
     * @param string $dataFilename Assume signature is detached when not null
     * @return GpgVerifyResult
     */
    public function verify($sign, $dataFilename = null)
    {
        if (is_null($dataFilename))
            return $this->execute(new GpgVerifyResult(), array('--verify'), $sign);

        // Handling detached verification
        $signFilename = tempnam(sys_get_temp_dir(), 'php-gnupg');
        file_put_contents($signFilename, $sign);
        $result = $this->execute(new GpgVerifyResult(), array('--verify', $signFilename, $dataFilename));
        unlink($signFilename);
        return $result;
    }

    /**
     * Encrypt/sign message
     * @param string $data data to encrypt
     * @param mixed $recipients Single key fingerprint string or array of multiple fingerprints
     * @param string $signKey Key ID for sign. If null, do not sign
     * @param string $passphrase Key passphrase
     * @param string $alwaysTrust When true, skip key validation and assume that used keys are always fully trusted.
     * @param string $outputFilename If not null, encrypted data will be written to file
     * @param string $binary If false, create ASCII armored output.
     * @param string $symmetric Encrypt with symmetric cipher only
     * @return GpgEncryptResult
     */
    public function encrypt($data, $recipients, $signKey = null, $passphrase = null,
                            $alwaysTrust = false, $outputFilename = null, $binary = false, $symmetric = false)
    {
        if (!is_array($recipients))
            $recipients = array($recipients);
        if ($symmetric)
            $args = array('--symmetric');
        else
        {
            $args = array('--encrypt');
            foreach ($recipients as $recipient)
            {
                $args[] = '--recipient';
                $args[] = $recipient;
            }
        }
        if (!$binary)
            $args[] = '--armor';
        if ($outputFilename)
        {
            // to avoid overwrite confirmation message
            if (file_exists($outputFilename))
                unlink($outputFilename);
            $args = array_merge($args, array('--output', $outputFilename));
        }
        if ($signKey)
            $args = array_merge($args, array('--sign', '--local-user', $signKey));
        if ($alwaysTrust)
            $args[] = '--always-trust';
        return $this->execute(new GpgEncryptResult(), $args, $data, $passphrase);
    }

    /**
     * Encrypt/sign file
     * Warning: Entire file will be loaded into memory!
     * @param mixed $recipients Single key fingerprint string or array of multiple fingerprints
     * @param string $signKey Key ID for sign. If null, do not sign
     * @param string $passphrase Key passphrase
     * @param string $alwaysTrust When true, skip key validation and assume that used keys are always fully trusted.
     * @param string $outputFilename If not null, encrypted data will be written to file
     * @param string $binary If false, create ASCII armored output.
     * @param string $symmetric Encrypt with symmetric cipher only
     * @return GpgEncryptResult
     */
    public function encryptFile($filename, $recipients, $signKey = null, $passphrase = null,
                                $alwaysTrust = false, $outputFilename = null, $binary = false, $symmetric = false)
    {
        return $this->encrypt(
            file_get_contents($filename),
            $recipients, $signKey, $passphrase,
            $alwaysTrust, $outputFilename, $binary, $symmetric
        );
    }

    /**
     * Decrypt/verify message
     * @param string $data Data to decrypt
     * @param string $passphrase Passphrase
     * @param string $sender Sender key ID. If null, do not verify
     * @param string $alwaysTrust When true, skip key validation and assume that used keys are always fully trusted.
     * @param string $outputFilename If not null, decrypted data will be written to file
     * @return GpgEncryptResult
     */
    public function decrypt($data, $passphrase, $sender = null, $alwaysTrust = false, $outputFilename = null)
    {
        $args = array('--decrypt');
        if ($outputFilename)
        {
            // to avoid overwrite confirmation message
            if (file_exists($outputFilename))
                unlink($outputFilename);
            $args = array_merge($args, array('--output', $outputFilename));
        }
        if (!is_null($sender))
            $args = array_merge($args, array('-u', $sender));
        if ($alwaysTrust)
            $args[] = '--always-trust';
        return $this->execute(new GpgEncryptResult(), $args, $data, $passphrase);
    }

    /**
     * Decrypt/verify file
     * Warning: Entire file will be loaded into memory.
     * @param string $filename Filename
     * @param string $passphrase Passphrase
     * @param string $sender Sender key ID. If null, do not verify
     * @param string $alwaysTrust When true, skip key validation and assume that used keys are always fully trusted.
     * @param string $outputFilename If not null, decrypted data will be written to file
     * @return GpgEncryptResult
     */
    public function decryptFile($filename, $passphrase, $sender = null, $alwaysTrust = false, $outputFilename = null)
    {
        return $this->decrypt(
            file_get_contents($filename),
            $passphrase, $sender, $alwaysTrust, $outputFilename
        );
    }
}
