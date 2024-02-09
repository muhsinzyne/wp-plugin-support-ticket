<?php

global $wpdb;
$table_name         = $wpdb->prefix . 'support_access_tokens';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $appIdRequested = $_POST['app_id'];
    $config         = [
        'digest_alg'       => 'sha512',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    // Generate the key pair
    $res = openssl_pkey_new($config);

    // Extract the private key
    openssl_pkey_export($res, $privateKey);

    // Extract the public key

    $publicKey = openssl_pkey_get_details($res)['key'];

    $data = [
        'publicKey'  => $publicKey,
        'privateKey' => $privateKey
    ];

    $table_name = $wpdb->prefix . 'support_access_tokens';

    $insert_result = $wpdb->insert(
        $table_name,
        [
            'public_key'   => $publicKey,
            'private_key'  => $privateKey,
            'app_name'     => $appIdRequested,
        ]
    );

    if ($insert_result === false) {
        // Handle insert error
        echo 'Error inserting keys into the table: ' . $wpdb->last_error;
    } else {
        echo 'Keys inserted into table successfully.';
    }
}

$accessTokens       = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);

function manipulatePublicKey($publicKey, $action = 'remove')
{
    // Define the header and footer lines
    $beginHeader = '-----BEGIN PUBLIC KEY-----';
    $endHeader   = '-----END PUBLIC KEY-----';

    // Check if the action is to remove headers
    if ($action === 'remove') {
        // Remove the headers
        $publicKey = str_replace($beginHeader, '', $publicKey);
        $publicKey = str_replace($endHeader, '', $publicKey);
    } elseif ($action === 'add') {
        // Add the headers if they don't already exist
        if (strpos($publicKey, $beginHeader) === false) {
            $publicKey = $beginHeader . "\n" . $publicKey;
        }
        if (strpos($publicKey, $endHeader) === false) {
            $publicKey .= "\n" . $endHeader;
        }
    }

    // Remove leading and trailing whitespace
    $publicKey = trim($publicKey);

    return $publicKey;
}

function validateKeyPair($publicKey, $privateKey)
{
    // Example validation logic
    // For demonstration purposes, you can check if the public key corresponds to the private key
    // In real-world scenarios, you might have more complex validation logic
    $res     = openssl_pkey_get_private($privateKey);
    $details = openssl_pkey_get_details($res);
    $valid   = ($details['key'] == $publicKey);

    return $valid;
}

?>


<div class="wrap">
    <h1>Access Tokens</h1>
    <table class="wp-list-table widefat striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>APP ID</th>
                <th>Public Key</th>
                <th>Private Key</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($accessTokens as $row) : ?>
            <tr>
                <td><?php echo esc_html($row['id']); ?></td>
                <td><?php echo esc_html($row['app_name']); ?></td>
                <td><?php echo base64_encode($row['public_key']); ?></td>
                <td><?php echo esc_html('********'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="wrap">
    <h1>Generate New Token</h1>
    <form method="post" action="">


        <label for="custom_meta_field">APP ID:</label>
        <input type="text" id="app_id" name="app_id" value="" placeholder="App Unique Identificaiton" />


        <button type="submit" name="submit" class="button button-primary">Generate New</button>
    </form>
</div>

<style>
.max-300 {
    max-width: 300px !important;
}
</style>