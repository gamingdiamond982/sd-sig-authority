<?php
/* 
 * This code is licensed under the GNU AFFERO GENERAL PUBLIC LICENSE
 * The license text can be found here https://www.gnu.org/licenses/agpl-3.0.txt
 *
 * This code was written by stoner and inspired by kuilin (https://kuilin.net/sdvote/?source)
 */


$priv_files_dir = "/var/www/sdvote/";


$config_file = fopen($priv_files_dir."config.json", "r");

if (!$config_file) {
    die("<script>alert('This site has been badly configured please contact the site admin'); </script>");
}


$config = json_decode(fread($config_file, filesize($priv_files_dir."config.json")), true);
fclose($config_file);


$discord_client_id = $config["discord"]["client_id"];
$discord_client_secret = $config["discord"]["client_secret"];
$discord_redirect_uri = $config["discord"]["redirect_uri"];
$discord_redirect = "https://discord.com/oauth2/authorize?client_id=".$discord_client_id."&response_type=code&redirect_uri=".urlencode($discord_redirect_uri)."&scope=identify";
$discord_api_uri = "https://discord.com/api/v10";

$reddit_client_id = $config["reddit"]["client_id"];
$reddit_client_secret = $config["reddit"]["client_secret"];
$reddit_redirect_uri = $config["reddit"]["redirect_uri"];
$reddit_qs = "client_id=".$reddit_client_id."&response_type=code&state=nada&redirect_uri=".urlencode($reddit_redirect_uri)."&duration=temporary&scope=identity";


if (isset($_GET["src"])) die(str_replace($discord_client_secret, "REDACTED", highlight_file(__FILE__, true)));



function exchange_oauth_code($code, $endpoint, $id, $secret, $uri ) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $post_data = array('grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $uri);
    $headers = [
        'Content-Type: application/x-www-form-urlencoded'
    ];
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_USERPWD, $id.':'.$secret);
    $result = curl_exec($ch);
    $result = json_decode($result, true);
    if (isset($result["error"])) {
        die("You broke her :( ");
    };
    return $result;
}


function exchange_discord_code($code) {
    global $discord_client_id, $discord_client_secret, $discord_redirect_uri;
    $result = exchange_oauth_code($code, "https://discord.com/api/v10/oauth2/token", $discord_client_id, $discord_client_secret, $discord_redirect_uri);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://discord.com/api/v10/users/@me");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer '.$result["access_token"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);

    return $result["id"];
}


function exchange_reddit_code($code) {
    global $reddit_client_id, $reddit_client_secret, $reddit_redirect_uri;
    $result = exchange_oauth_code($code, "https://www.reddit.com/api/v1/access_token", $reddit_client_id, $reddit_client_secret, $reddit_redirect_uri);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://oauth.reddit.com/api/v1/me");
    curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer ".$result["access_token"]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);

    return "u/".$result["name"];

}



function load_file($path) {
    global $priv_files_dir;
    $keyfile = fopen($priv_files_dir.$path, "r");
    $key = fread($keyfile, filesize($priv_files_dir.$path));
    fclose($keyfile);
    return $key;
}


function get_secret_key() {
    return load_file("priv.key");
}


function roll_secret_key() {
    global $priv_files_dir;
    rename($priv_files_dir."priv.key", $priv_files_dir."priv.key.old");
    $new_key = bin2hex(random_bytes(32)); // using sha256 so (256 bit/32 byte) key needed
    $file = fopen($priv_files_dir."priv.key", "w");
    fwrite($file, $new_key);
    fclose($file);
}



function sign($message) {
    $key = get_secret_key();
    return hash_hmac("sha256", $message, $key);
}

function verify($UUVT) {
    $parts = explode(".", $UUVT);
    $signature = $parts[sizeof($parts)-1];
    unset($parts[sizeof($parts)-1]);
    $msg = implode(".", $parts);
    return sign($msg) == $signature;
}


function anonymise($message) {
    return substr(sign($message), 0, 20);
}

?>
