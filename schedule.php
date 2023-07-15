<?php

$servername = "Localhost";
$hostusername = "";
$password = "";
$dbname = "";

$bot_token = "";
$preurl = "https://api.telegram.org/bot";
$word_api_key = "";

///////////////////////////

$rapid_api_token = "";


//////////////////////////


$url = $preurl . $bot_token;


function connect_db()
{
    $conn = mysqli_connect(
        $GLOBALS["servername"],
        $GLOBALS["hostusername"],
        $GLOBALS["password"],
        $GLOBALS["dbname"]
    );
    $conn->query("set NAMES utf8");
    if ($conn->connect_error) {
        $message = "Failed" . $conn->connect_error;
    } else {
        return $conn;
    }
}

function send_reply($url, $post_params)
{

    $cu = curl_init();
    curl_setopt($cu, CURLOPT_URL, $url);
    curl_setopt($cu, CURLOPT_POSTFIELDS, $post_params);
    curl_setopt($cu, CURLOPT_RETURNTRANSFER, true);  // get result
    $result = curl_exec($cu);
    curl_close($cu);
    return $result;
}

function show_menu($reply_kb_options, $reply, $post_params = 0)
{
    $json_kb = json_encode($reply_kb_options);
    $url = $GLOBALS['url'] . "/sendMessage";
    if ($post_params == 0) {
        $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $reply, 'reply_markup' => $json_kb];
        $result = send_reply($url, $post_params);
    } else {
    }

    return $result;
}

function inline_keybord($inline_kb_options, $reply)
{
    $json_kb = json_encode($inline_kb_options);
    $url = $GLOBALS['url'] . "/sendMessage";
    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $reply, 'reply_markup' => $json_kb , 'parse_mode' => "HTML"];
    $result = send_reply($url, $post_params);
    return $result;
}

/////

function translate($text, $user_id)
{

    $text = strtolower($text);

    $conn = connect_db();
    $result = $conn->query("SELECT * FROM users WHERE user_id = '$user_id' ");

    if ($result->num_rows == 0 || $result->fetch_assoc()['target_lang'] == "") {

        return ["", "", "", "f"];
    } else {

        $target_lang = $conn->query("SELECT * FROM users WHERE user_id = '$user_id' ")->fetch_assoc()["target_lang"];

        $result = $conn->query("SELECT * FROM translations WHERE input_content = '$text' AND target_lang = '$target_lang' ");

        if ($result->num_rows == 0) {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://microsoft-translator-text.p.rapidapi.com/translate?api-version=3.0&to=" . $target_lang . "&textType=plain&profanityAction=NoAction",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => "[\r
                {\r
                    \"Text\": \"$text\"\r
                }\r
            ]",
                CURLOPT_HTTPHEADER => [
                    "content-type: application/json",
                    "x-rapidapi-host: microsoft-translator-text.p.rapidapi.com",
                    "x-rapidapi-key: 9335c54581msh921529f6b3e0dd6p180416jsn9f01106406b9"
                ],
            ]);


            $response = curl_exec($curl);
            $res_array = json_decode($response, true);
            $err = curl_error($curl);
            curl_close($curl);

            if (isset($res_array[0]["translations"][0]["text"])) {

                $translated_text = $res_array[0]["translations"][0]["text"];
                $detected_language = $res_array[0]["detectedLanguage"]["language"];
                $conn->query("INSERT INTO translations(input_content, target_lang , translation , detected_language)
                VALUES ('$text' , '$target_lang' , '$translated_text' , '$detected_language' )");

                $id = $conn->query("SELECT * FROM translations WHERE input_content = '$text' AND target_lang = '$target_lang' ")->fetch_assoc()['id'];
            } else {
                $translated_text = "";
                $detected_language = "";
            }
        } else {
            $row = $result->fetch_assoc();
            $translated_text = $row['translation'];
            $detected_language = $row['detected_language'];
            $id = $row['id'];
        }

        return [$translated_text, $detected_language, $id];
    }
}

function definition($word)
{
    $conn = connect_db();

    $row = $conn->query("SELECT * FROM words WHERE word= '$word' ")->fetch_assoc();
    $id = $row['id'];
    $found = $row['found'];

    if ($found == "true") {

        $str = "\n❇️ Definitions : \n";
        $result = $conn->query("SELECT * FROM words_definitions WHERE word_id = '$id' ");
        $x = 0;
        while ($row = $result->fetch_assoc()) {
            $x += 1;
            $str = $str . strval($x) . " : (" . "<i>" . $row["part_of_speech"] . "</i>) : " . $row['definition'] . "\n";
        }

        $result = $conn->query("SELECT * FROM words_examples WHERE word_id = '$id' ");
        if ($result->num_rows > 0) {
            $str = $str . "\n💡 Example of usage :\n";
        }

        while ($row = $result->fetch_assoc()) {

            $str = $str . "📝 " . $row["example"] . "\n";
        }

        return [$str, true, $id];
    } else {
        return ["", false, $id];
    }
}

function handle_definition($word)
{

    $word = strtolower($word);

    $api_key = $GLOBALS['word_api_key'];
    $definition_url = "https://api.wordnik.com/v4/word.json/" . $word . "/definitions?limit=4&includeRelated=false&useCanonical=false&includeTags=false&api_key=" . $api_key;
    $top_example_url = "https://api.wordnik.com/v4/word.json/" . $word . "/topExample?useCanonical=false&api_key=" . $api_key;
    $examples_url = "https://api.wordnik.com/v4/word.json/" . $word . "/examples?includeDuplicates=false&useCanonical=false&limit=4&api_key=" . $api_key;



    $conn = connect_db();
    $result = $conn->query("SELECT * FROM words WHERE word = '$word' ");

    if ($result->num_rows == 0) {

        $cURLConnection = curl_init();
        curl_setopt($cURLConnection, CURLOPT_URL, $definition_url);
        curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($cURLConnection);
        curl_close($cURLConnection);
        $res_array = json_decode($response, true);

        if (isset($res_array['error'])) {

            $conn->query("INSERT INTO words(word, isset_voice , found) VALUES ('$word', 'false' , 'false' )");
            $id = mysqli_insert_id($conn);
            return ["", false, $id];
        } else {

            $conn->query("INSERT INTO words(word, isset_voice , found) VALUES ('$word', 'false' , 'true' )");
            $id = mysqli_insert_id($conn);

            foreach ($res_array as $d) {

                if (isset($d['partOfSpeech']) && isset($d['text'])) {
                    $part_of_speech = $d['partOfSpeech'];
                    $definition = $d['text'];
                    $conn->query("INSERT INTO words_definitions(word_id , definition , part_of_speech ) VALUES ('$id' , '$definition' 
                    , '$part_of_speech' ) ");
                }
            }

            $cURLConnection = curl_init();
            curl_setopt($cURLConnection, CURLOPT_URL, $top_example_url);
            curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($cURLConnection);
            curl_close($cURLConnection);
            $res_array = json_decode($response, true);

            if (isset($res_array['text'])) {

                $top_example = $res_array['text'];
                $conn->query("INSERT INTO words_examples(word_id , example ) VALUES ('$id' , '$top_example' ) ");
            } else {

                $cURLConnection = curl_init();
                curl_setopt($cURLConnection, CURLOPT_URL, $examples_url);
                curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($cURLConnection);
                curl_close($cURLConnection);
                $res_array = json_decode($response, true);
            }


            return definition($word);
        }
    } else {
        return definition($word);
    }
}

function pronunciation($word)
{

    $conn = connect_db();
    $word = strtolower($word);

    $api_key = $GLOBALS['word_api_key'];
    $pronunciation_url = "https://api.wordnik.com/v4/word.json/" . $word . "/audio?useCanonical=false&limit=1&api_key=" . $api_key;

    $row = $conn->query("SELECT * FROM words WHERE word= '$word' ")->fetch_assoc();
    $id = $row['id'];

    $cURLConnection = curl_init();
    curl_setopt($cURLConnection, CURLOPT_URL, $pronunciation_url);
    curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($cURLConnection);
    curl_close($cURLConnection);
    $res_array = json_decode($response, true);

    if (isset($res_array[0]['fileUrl'])) {

        $file_url = $res_array[0]['fileUrl'];
        return [$id, $file_url];
    } else {
        return [$id, "", "f"];
    }
}




//// handle cron job


$conn = connect_db();
$hour = date("H");


$result = $conn->query("SELECT * FROM schedules");
$message = $result->num_rows; 
$post_params = ['chat_id' =>  631718450 , 'text' => $message];
$url = $GLOBALS["url"] . "/sendMessage";
$response = send_reply($url, $post_params);

$message = $result->num_rows; 
$post_params = ['chat_id' =>  105345575 , 'text' => $message];
$url = $GLOBALS["url"] . "/sendMessage";
$response = send_reply($url, $post_params);
while( $row = $result->fetch_assoc() ){

    // sleep(2);
    // $message = $row["user_id"];
    // $post_params = ['chat_id' =>  $row["user_id"] , 'text' => $message];
    // $url = $GLOBALS["url"] . "/sendMessage";
    // $response = send_reply($url, $post_params);

}



?>