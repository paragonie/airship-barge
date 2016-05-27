<?php
declare(strict_types=1);
namespace Airship\Barge\Commands;

use \Airship\Barge as Base;
use \ParagonIE\Halite\Asymmetric\Crypto as Asymmetric;
use \ParagonIE\Halite\KeyFactory;

class Key extends Keygen
{
    public $essential = false;
    public $name = 'Key Management';
    public $description = 'Manage signing keys';
    public $display = 3;

    /**
     * Execute the key command
     *
     * @param array $args - CLI arguments
     * @echo
     * @return null
     */
    public function fire(array $args = [])
    {
        $argc = \count($args);
        if ($argc === 0) {
            parent::fire();
            return;
        }
        $argPass = \array_slice($args, 1);
        switch ($args[0]) {
            case 'generate':
                parent::fire($argPass);
                break;
            case 'revoke':
                $this->handleKeyRevoke($argPass);
                break;
        }
    }

    /**
     * We are revoking a key.
     *
     * @param array $args
     * @throws \Exception
     * @return mixed
     */
    protected function handleKeyRevoke(array $args)
    {
        if (count($this->config['suppliers']) === 1) {
            $supplier = \count($args) > 0
                ? $args[0]
                : \array_keys($this->config['suppliers'])[0];
        } else {
            $supplier = \count($args) > 0
                ? $args[0]
                : $this->prompt("Please enter the name of the supplier: ");
        }

        if (!\array_key_exists($supplier, $this->config['suppliers'])) {
            echo 'Please authenticate before attempting to revoke keys.', "\n";
            echo 'Run this command: ', $this->c['yellow'], 'barge login', $this->c[''], "\n";
            exit(255);
        }

        $masterKeys = [];
        $keyList = [];
        foreach ($this->config['suppliers'][$supplier]['signing_keys'] as $key) {
            if ($key['type'] === 'master') {
                if (!empty($key['salt'])) {
                    $masterKeys[] = $key;
                } else {
                    $keyList[] = $key;
                }
            } else {
                $keyList[] = $key;
            }
        }
        if (empty($masterKeys)) {
            echo 'No usable master keys found. Make sure the salt is loaded locally.', "\n";
            exit(255);
        }
        if (empty($keyList)) {
            // If and only if you have nothing more to revoke, allow revoking the master key:
            $keyList = $masterKeys;
        }
        if (\count($masterKeys) === 1) {
            $masterKey = $masterKeys[0];
        } else {
            $masterKey = $this->selectKeyFromList('Select your master key: ', $masterKeys);

            // Add other master keys to the list
            foreach ($masterKeys as $key) {
                if ($key['public_key'] !== $masterKey['public_key']) {
                    $keyList[] = $key;
                }
            }
        }

        if (\count($keyList) === 1) {
            $revokingKey = $keyList[0];
        } else {
            $revokingKey = $this->selectKeyFromList('Select which key to revoke: ', $keyList);
        }

        $confirm_revoke = null;
        while ($confirm_revoke === null) {
            $choice = $this->prompt('Are you sure you wish to revoke this key? (y/N): ');
            switch ($choice) {
                case 'YES':
                case 'yes':
                case 'Y':
                case 'y':
                    $confirm_revoke = true;
                    break;
                case 'N':
                case 'NO':
                case 'n':
                case 'no':
                case '': // Just pressing enter means "don't store it"!
                    $confirm_revoke = false;
                    break;
                default:
                    echo "\n", $this->c['yellow'], 'Invalid response. Please enter yes or no.', $this->c[''], "\n";
            }
        }

        // This is what get signed by our master key:
        $message = [
            'action' =>
                'REVOKE',
            'date_revoked' =>
                \date('Y-m-d\TH:i:s'),
            'public_key' =>
                $revokingKey['public_key'],
            'supplier' =>
                $supplier
        ];
        $messageToSign = \json_encode($message);

        $iter = false;
        do {
            if ($iter) {
                echo 'Incorrect password.', "\n";
            }
            $password = $this->silentPrompt('Enter the password for your master key: ');
            if (empty($password)) {
                // Okay, let's cancel.
                throw new \Exception('Aborted.');
            }
            $masterKeyPair = KeyFactory::deriveSignatureKeyPair(
                $password,
                \Sodium\hex2bin($masterKey['salt']),
                false,
                KeyFactory::SENSITIVE
            );
            \Sodium\memzero($password);

            $masterPublicKeyString = \Sodium\bin2hex(
                $masterKeyPair
                    ->getPublicKey()
                    ->getRawKeyMaterial()
            );
            $iter = true;
        } while (!\hash_equals($masterKey['public_key'], $masterPublicKeyString));

        $signature = Asymmetric::sign(
            $messageToSign,
            $masterKeyPair->getSecretKey()
        );

        $response = $this->sendRevocation(
            $supplier,
            $message,
            $signature,
            $masterPublicKeyString
        );

        if ($response['status'] === 'OK') {
            foreach ($this->config['suppliers'][$supplier]['signing_keys'] as $i => $key) {
                if ($key['public_key'] === $message['public_key']) {
                    unset($this->config['suppliers'][$supplier]['signing_keys'][$i]);
                }
            }
        }
        return $response;
    }

    /**
     * Send the upstream revocation notice
     *
     * @param string $supplier
     * @param array $data
     * @param string $masterSignature
     * @param string $masterPublicKey
     * @return array
     * @throws \Exception
     */
    protected function sendRevocation(
        string $supplier,
        array $data = [],
        string $masterSignature,
        string $masterPublicKey
    ): array {
        list ($skyport, $publicKey) = $this->getSkyport();

        $postData = [
            'token' => $this->getToken($supplier),
            'message' => $data,
            'master' => [
                // Only used for "which key?", don't trust this input
                'public_key' => $masterPublicKey,
                // Should validate date_generated and public key
                'signature' => $masterSignature
            ]
        ];

        // The user must opt in for this to be invoked:
        if ($data['store_in_cloud']) {
            $postData['stored_salt'] = $data['salt'];
        }
        return Base\HTTP::postSignedJSON(
            $skyport . 'key/revoke',
            $publicKey,
            $postData
        );
    }

    /**
     * Select a key from a list
     *
     * @param string $prompt
     * @param array $keys
     * @return array
     * @throws \Exception
     */
    protected function selectKeyFromList(
        string $prompt = 'Select one: ',
        array $keys = []
    ): array {
        $countKeys = \count($keys);
        while (true) {
            for ($i = 1; $i <= $countKeys; ++$i) {
                echo "\t" . \str_pad($i, 4, ' ', STR_PAD_LEFT) . "\t{$keys[$i]['public_key']}\n";
            }
            $idx = $this->prompt($prompt);
            if (empty($idx)) {
                throw new \Exception('Aborted.');
            }
            $idx += 0;
            if ($idx > 0 && $idx <= $countKeys) {
                return $keys[$idx - 1];
            }
        }
        throw new \Exception('Aborted.');
    }
}
