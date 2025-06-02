<?php
/* 
 * This code is licensed under the GNU AFFERO GENERAL PUBLIC LICENSE
 * The license text can be found here https://www.gnu.org/licenses/agpl-3.0.txt
 *
 * This code was written by stoner and inspired by kuilin (https://kuilin.net/sdvote/?source)
 */

if (isset($_GET["src"])) die(htmlspecialchars(highlight_file(__FILE__, true)));


require 'util.php';


$discord_redirect_uri = $config["discord"]["admin_redirect"];
$discord_redirect = "https://discord.com/oauth2/authorize?client_id=".$discord_client_id."&response_type=code&redirect_uri=".urlencode($discord_redirect_uri)."&scope=identify";


if (isset($_GET["login"])) {
    header("Location: ".$discord_redirect);
    die();
}

if (isset($_GET["code"])) {
    $uid = exchange_discord_code($_GET["code"]);
    $msg = $uid.".".time();
    $secret = $msg.".".sign($msg);
    setcookie("auth", $secret, time() + 60*60*24); // cookie expires in one day
    header("Location: ./admin.php");
    die();
}


if (!file_exists($priv_files_dir."admins.json")) {
    $empty_array = json_encode([], JSON_PRETTY_PRINT);
    $file = fopen($priv_files_dir."admins.json", "w");
    fwrite($file, $empty_array);
    fclose($file);
}



if (!isset($_COOKIE["auth"])) {
    header("Location: ./admin.php?login");
    die();
} else {
    $auth = $_COOKIE["auth"];
    if (!(verify($auth))) {
        header("Location: ./admin.php?login");
        die();
    }

    $admins = json_decode(file_get_contents($priv_files_dir."admins.json"));
    $discord_id = explode(".", $auth)[0];
    
    if (!in_array($discord_id, $admins)) {
        die("<script>alert('You don\\'t have permission to view this page'); window.location = './';</script>");
    }

}

// from this point down the use has been authenticated do not put privelidged code above this point.



if (isset($_POST["discordid"])) {
    if (($index = array_search($_POST["discordid"], $admins)) !== false ) {
        unset($admins[$index]);
    } else {
        array_push($admins, $_POST["discordid"]);
    }
    $admins = array_values($admins);
    $file = fopen($priv_files_dir."admins.json", "w");
    fwrite($file, json_encode($admins, JSON_PRETTY_PRINT));
    fclose($file);
    header("Location: ./admin.php?admins");
    die();
} 


if (isset($_GET["rollkey"])) {
    roll_secret_key();
    header("Location: ./admin.php?oldkey");
    die();
}

?>
<!DOCTYPE html>
<html>
    <head>
    <link rel="stylesheet" href="styles.css">
    <script>
        var warned = false;
        function roll() {
            if (!warned) {
                alert("Warning: rolling the secret key will remove the old one, and should only be done once the old key has been archived. \n Press the roll button again to confirm rolling.");
                warned = true;
            } else {
                window.location = './admin.php?rollkey';
            }
        }
    </script>
    </head>
    <body>
        <div class="title">
            Sim Democracy Voter Portal Admin interface.
        </div>
        <div class="content">
            <?php if (isset($_GET["admins"])) {?>
                <b>Here is the current list of admin ids</b>
                <textarea id="admins" rows="10" readonly><?php
                    echo htmlspecialchars(json_encode($admins, JSON_PRETTY_PRINT));
                ?></textarea>
                <b>Add/Remove an id from this list</b><br/>
                <p>If the id is present in the list it will be removed otherwise it will be added, note that verification of ids is left at the discretion of the operator, the presence of invalid ids is not harmful other than that it looks unclean.  </p>
                <form method="POST" action="./admin.php">
                    <label for="discordid">Discord ID:</label>
                    <input type="text" name="discordid">
                    <input type="submit" value="Update">
                </form>
                <button onclick="window.location = './admin.php'">back</button>
            <?php } else if (isset($_GET["oldkey"])) { ?>
                <b>Here is the key used in the last election</b>
                <textarea id="oldkey" rows=2 onclick="this.select()" readonly><?php
                    echo htmlspecialchars(load_file($priv_files_dir."priv.key.old"));
                ?></textarea>
                <b>Warning: rolling the key will remove the above key from the server</b>
                <p>Ensure you no longer need it and it has been archived by the DoVR before rolling the key</p>
                <button onclick="roll()">Roll secret key</button><br/>
                <button onclick="window.location = './admin.php'">back</button>

            <?php } else {?>
                <b>Welcome to the Admin interface here are some buttons you can press:</b><br/>
                <button onclick="window.location = './admin.php?admins'">Update admin records</button><br/>
                <button onclick="window.location = './admin.php?oldkey'">View old private key/roll new one</button> <br/>
                <a href=".">logout</a>
            <?php }?>
        </div>
    </body>
</html>

