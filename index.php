<?php
/* 
 * This code is licensed under the GNU AFFERO GENERAL PUBLIC LICENSE
 * The license text can be found here https://www.gnu.org/licenses/agpl-3.0.txt
 *
 * This code was written by stoner and inspired by kuilin (https://kuilin.net/sdvote/?source)
 */

if (isset($_GET["src"])) die(str_replace($discord_client_secret, "REDACTED", highlight_file(__FILE__, true)));
// you can view the source of util.php by getting ./util.php?src
require 'util.php';

?>
<!DOCTYPE html>
<html>
    <head>
        <link rel="stylesheet" href="./styles.css">
    </head>
    <body>
        <div class="title">
            Sim Democracy Voter Portal
        </div>
        <div class="content">
            <?php if (isset($_GET["anons"])) { ?>
                Anons: <br/>
                <textarea id="anons" onclick="this.select()" readonly><?php 
                $anons = fopen("anons.json", "r") or die("Error: Could not open file");
                echo htmlspecialchars(fread($anons, filesize("anons.json")));
                fclose($anons);
                ?></textarea>
            <?php } else if (isset($_GET["vote"])) { ?>
                <b>Choose the login link associated with your voter registration</b><br/>
                <p>
                    <a href="<?php echo $discord_redirect; ?>">Login with discord</a><br/>
                    <a href="<?php echo "https://reddit.com/api/v1/authorize?".$reddit_qs; ?>">Login with reddit</a>
                    (<a href="<?php echo "https://reddit.com/api/v1/authorize.compact?".$reddit_qs ?>">mobile link</a>)
                </p>
            <?php } else if (isset($_GET["code"])) { ?>
                <b>Your vote token: </b>
                <textarea onclick="this.select()" readonly><?php
                if (isset($_GET["discord"])) {
                    $uid = exchange_discord_code($_GET["code"]);
                } else {
                    $uid = exchange_reddit_code($_GET["code"]);
                }
                $msg = anonymise($uid).".".time();
                echo htmlspecialchars($msg.".".sign($msg));
                ?>
                </textarea>
                <a href=".">Logout</a>
            <?php } else {?>
                <b>Welcome to SimDem's signatory authority</b><br/>
                <a href=".?vote">Generate a vote token</a><br/>
                <a href="./admin.php">DoVR portal</a>
            <?php }?> 
        </div>
    </body>

</html>

