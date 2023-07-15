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
$update = file_get_contents("php://input");
$update = json_decode($update, true);
if (isset($update["message"])) {
    $chat_id = $update["message"]["chat"]["id"];
    $chat_type = $update["message"]["chat"]["type"];
} elseif (isset($update["callback_query"])) {
    $chat_id = $update["callback_query"]["message"]["chat"]["id"];
    $chat_type = $update["message"]["chat"]["type"];
}


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
    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $reply, 'reply_markup' => $json_kb, 'parse_mode' => "HTML"];
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

        $str = $word ;
        $result = $conn->query("SELECT * FROM words_definitions WHERE word_id = '$id' ");
        if ($result->num_rows > 0) {
            $str = $str."\n❇️ Definitions : \n";
        }
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


function handle_query($inline_query, $inline_query_id, $user_id)
{

    $conn = connect_db();
    $result = $conn->query("SELECT * FROM requests WHERE user_id = '$user_id'");

    if ($result->num_rows == 0) {

        $date = date("d");
        $conn->query("INSERT INTO requests(user_id , day , daily_usage
        )VALUES ('$user_id', '$date' , 1 )");
    } else {

        if (strpos($inline_query, '-') !== false) {

            if (substr_count($inline_query, "-") < 2) {

                if (strpos($inline_query, '-def') !== false) {
                    // definition
                    $inline_query = str_replace("-def ", "", $inline_query);

                    $array = handle_definition($inline_query);

                    if ($array[1] === false) {

                        $result_array = [
                            [
                                'type'        => "article",
                                'id'          =>   $array[2],
                                'title'       => "En: " . $inline_query,
                                'description' => "Nothing was found .",
                                'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                'input_message_content' => ['message_text' => "Entry content : " . $inline_query . "\n" . "\n" . "Nothing was found."],
                            ]
                        ];

                        $json_res_array = json_encode($result_array);

                        $url = $GLOBALS['url'] . "/answerInlineQuery";
                        $post_params = [
                            'inline_query_id' => $inline_query_id,
                            'results'         => $json_res_array,
                        ];
                        send_reply($url, $post_params);
                    } else {

                        // return def

                        $inline_keyboard = [

                            [
                                ['text' => "Add to your word list.", 'callback_data' => implode("#!", ["add_word", $inline_query])],
                                ['text' => "Get pronunciation", 'switch_inline_query_current_chat' => "-pron " . $inline_query],
                            ],
                        ];

                        $inline_kb_options = [
                            'inline_keyboard' => $inline_keyboard
                        ];

                        $result_array = [
                            [
                                'type'        => "article",
                                'id'          =>   $array[2],
                                'title'       => "En: " . $inline_query,
                                'description' => "Click to know about definitions.",
                                'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                'input_message_content' => ['message_text' => "Entry content : " . $inline_query . "\n" . $array[0], 'parse_mode' => 'HTML'],
                                'reply_markup' => $inline_kb_options,
                            ]
                        ];

                        $json_res_array = json_encode($result_array);

                        $url = $GLOBALS['url'] . "/answerInlineQuery";
                        $post_params = [
                            'inline_query_id' => $inline_query_id,
                            'results'         => $json_res_array,
                        ];
                        send_reply($url, $post_params);
                    }
                } elseif (strpos($inline_query, '-pron') !== false) {
                    $inline_query = str_replace("-pron ", "", $inline_query);
                    $array = pronunciation($inline_query);
                    if (isset($array[2])) {
                        $result_array = [
                            [
                                'type'        => "article",
                                'id'          =>   9000003,
                                'title'       => "En: " . $inline_query,
                                'description' => "Nothing was found",
                                'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                'input_message_content' => ['message_text' => "Nothing was found."],
                            ]
                        ];
                    } else {
                        $result_array = [
                            [
                                'type'        => "audio",
                                'id'          => strval($array[0]),
                                'title'       => $inline_query,
                                'caption' => "pronunciation of " . $inline_query,
                                'audio_url'   => $array[1],

                            ]
                        ];

                        $json_res_array = json_encode($result_array);

                        $url = $GLOBALS['url'] . "/answerInlineQuery";
                        $post_params = [
                            'inline_query_id' => $inline_query_id,
                            'results'         => $json_res_array,
                        ];
                    }

                    send_reply($url, $post_params);
                } else {
                    $result_array = [
                        [
                            'type'        => "article",
                            'id'          => 9000001,
                            'title'       => "En: " . $inline_query,
                            'description' => "Unknown entry .",
                            'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                            'input_message_content' => ['message_text' => "Entry content : " . $inline_query . "\n" . "\n" . "Unknown entry . Please use « -def » for definition."],
                        ]
                    ];

                    $json_res_array = json_encode($result_array);

                    $url = $GLOBALS['url'] . "/answerInlineQuery";
                    $post_params = [
                        'inline_query_id' => $inline_query_id,
                        'results'         => $json_res_array,
                    ];
                    send_reply($url, $post_params);
                }
            } else {

                $result_array = [
                    [
                        'type'        => "article",
                        'id'          => 9000002,
                        'title'       => "Input: " . $inline_query,
                        'description' => "Unknown entry .",
                        'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                        'input_message_content' => ['message_text' => "Unknown entry . Please use « -def » for definition."],
                    ]
                ];

                $json_res_array = json_encode($result_array);

                $url = $GLOBALS['url'] . "/answerInlineQuery";
                $post_params = [
                    'inline_query_id' => $inline_query_id,
                    'results'         => $json_res_array,
                ];
                send_reply($url, $post_params);
            }
        } else {

            $row = $result->fetch_assoc();

            if (date('d') == $row['day']) {

                $daily_usage = $row['daily_usage'];

                if ($daily_usage > 4000) {

                    $result_array = [
                        [
                            'type'        => "article",
                            'id'          => 9000005,
                            'title'       => "En: " . $inline_query,
                            'description' => "usage limit exceeded.",
                            'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                            'input_message_content' => ['message_text' => "Entry content : " . $inline_query . "\n" . "\n" . "Daily usage limit exceeded (4000 character)."],
                        ]
                    ];

                    $json_res_array = json_encode($result_array);

                    $url = $GLOBALS['url'] . "/answerInlineQuery";

                    $post_params = [
                        'inline_query_id' => $inline_query_id,
                        'results'         => $json_res_array,
                    ];
                    send_reply($url, $post_params);
                } else {

                    $query_array = explode(" ", $inline_query);

                    if (count($query_array) > 2) {
                        $result_array = [
                            [
                                'type'        => "article",
                                'id'          => 9000006,
                                'title'       => "En: " . $inline_query,
                                'description' => "Invalid entry",
                                'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                'input_message_content' => ['message_text' => "Entry content : " . $inline_query . "\n" . "\n" . "Invali entry !" . "\n" . "Inline mode supports single words and phrasal verbs translation . Use PV to translate sentences."],
                            ]
                        ];

                        $json_res_array = json_encode($result_array);

                        $url = $GLOBALS['url'] . "/answerInlineQuery";

                        $post_params = [
                            'inline_query_id' => $inline_query_id,
                            'results'         => $json_res_array,
                        ];
                        send_reply($url, $post_params);
                    } else {

                        $daily_usage = $daily_usage + strlen($inline_query);
                        if ($conn->query("UPDATE requests SET daily_usage= '$daily_usage' WHERE user_id = '$user_id'  ") && $inline_query != "") {
                            $translation = translate($inline_query, $user_id);
                            $translated_text = $translation[0];
                            $input_lan = $translation[1];



                            if ($translated_text != '') {
                                $t = $conn->query("SELECT * FROM users WHERE user_id='$user_id' ")->fetch_assoc()['target_lang'];
                                $result_array = [
                                    [
                                        'type'        => "article",
                                        'id'          => $translation[2],
                                        'title'       => $input_lan . ": " . $inline_query,
                                        'description' => $t . ": " . $translated_text,
                                        'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                        'input_message_content' => ['message_text' => "entry content : " . $inline_query . "\n" . "Translation : " . $translated_text],
                                    ]
                                ];

                                $json_res_array = json_encode($result_array);

                                $url = $GLOBALS['url'] . "/answerInlineQuery";

                                $post_params = [
                                    'inline_query_id' => $inline_query_id,
                                    'results'         => $json_res_array,
                                ];
                                send_reply($url, $post_params);
                            } elseif (isset($translation[3])) {

                                $inline_keyboard = [

                                    [
                                        ['text' => "Choose now", 'url' => "http://t.me/transl2bot"],
                                    ],
                                ];

                                $inline_kb_options = [
                                    'inline_keyboard' => $inline_keyboard
                                ];


                                $result_array = [
                                    [
                                        'type'        => "article",
                                        'id'          => 9000004,
                                        'title'       => "Input: " . $inline_query,
                                        'description' => "Target language is not specified",
                                        'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                        'input_message_content' => ['message_text' => "Input: " . $inline_query . "\n\n" . "⚠️ Target language is not specified. Click the link below to choose which language you wanna translate to."],
                                        'reply_markup' => $inline_kb_options,
                                    ]
                                ];

                                $json_res_array = json_encode($result_array);

                                $url = $GLOBALS['url'] . "/answerInlineQuery";

                                $post_params = [
                                    'inline_query_id' => $inline_query_id,
                                    'results'         => $json_res_array,
                                ];
                                send_reply($url, $post_params);
                            } else {
                                $result_array = [
                                    [
                                        'type'        => "article",
                                        'id'          => 9000003,
                                        'title'       => "Input: " . $inline_query,
                                        'description' => "Nothing was found",
                                        'thumb_url'   => "https://mahdishokrzadeh.ir/translatorbot/files/icon.png",
                                        'input_message_content' => ['message_text' => "Nothing was found."],

                                    ]
                                ];

                                $json_res_array = json_encode($result_array);

                                $url = $GLOBALS['url'] . "/answerInlineQuery";

                                $post_params = [
                                    'inline_query_id' => $inline_query_id,
                                    'results'         => $json_res_array,
                                ];
                                send_reply($url, $post_params);
                            }
                        }
                    }
                }
            } else {

                $date = date("d");
                $conn->query("UPDATE requests SET day= '$date' , daily_usage=1 WHERE user_id = '$user_id'  ");
            }
        }
    }


    // $s =  rawurlencode($inline_query);
    // $curl = curl_init();

    // curl_setopt_array($curl, [
    // CURLOPT_URL => "https://google-translate1.p.rapidapi.com/language/translate/v2",
    // CURLOPT_RETURNTRANSFER => true,
    // CURLOPT_FOLLOWLOCATION => true,
    // CURLOPT_ENCODING => "",
    // CURLOPT_MAXREDIRS => 10,
    // CURLOPT_TIMEOUT => 30,
    // CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    // CURLOPT_CUSTOMREQUEST => "POST",
    // CURLOPT_POSTFIELDS => "q=".$s."&target=fa&source=en",
    // CURLOPT_HTTPHEADER => [
    // 	"accept-encoding: application/gzip",
    // 	"content-type: application/x-www-form-urlencoded",
    // 	"x-rapidapi-host: google-translate1.p.rapidapi.com",
    // 	"x-rapidapi-key: 9335c54581msh921529f6b3e0dd6p180416jsn9f01106406b9"
    // ],
    // ]);

    // $response = curl_exec($curl);
    // $array = json_decode($response, true);
    // $translated_text = $array['data']['translations'][0]['translatedText'] ;
    // $err = curl_error($curl);
    // curl_close($curl);

    // $result_array = [
    //     [
    //         'type'        => "article" ,
    //         'id'          => "1"       ,             
    //         'title'       => "En: " . $inline_query ,
    //         'description' => "Fa: " . $translated_text ,
    //         'input_message_content' => [ 'message_text' => "Entery content : ".$inline_query."\n"."Translation to Persian : ".$translated_text ] ,
    //     ]
    // ];

    // $json_res_array = json_encode($result_array);

    // $url = $GLOBALS['url']."/answerInlineQuery";

    // $post_params = [ 
    // 'inline_query_id' => $inline_query_id , 
    // 'results'         => $json_res_array , 
    // ];
    // send_reply($url, $post_params);


}


function response($text, $user_id, $username, $name)
{

    $conn = connect_db();
    if (strpos($text, '/del ') !== false) {
        $text = str_replace("/del ", "", $text);
        if ($conn->query("DELETE FROM users_list_words WHERE word='$text' AND user_id='$user_id' ") === true) {
            $message = $text . " was successfuly deleted from your list !";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
        } else {
            $message = "Error happend while processing . Check your entry is correct!";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
        }

    } 
        // elseif($text === "/set_daily_schedule"){
        //     $result = $conn->query("SELECT * FROM schedules WHERE user_id = '$user_id' ");
        //     if($result->num_rows === 0){
        //         $inline_keyboard = [

        //             [
        //                 ['text' => "1 word", 'callback_data' => implode("#!", ["set_daily_schedule", 1])],
        //                 ['text' => "2 word", 'callback_data' => implode("#!", ["set_daily_schedule", 2])],
        //                 ['text' => "3 word", 'callback_data' => implode("#!", ["set_daily_schedule", 3])],

        //             ],
        //             [
        //                 ['text' => "5 word", 'callback_data' => implode("#!", ["set_daily_schedule", 4])],
        //                 ['text' => "4 word", 'callback_data' => implode("#!", ["set_daily_schedule", 5])],
        //             ],
        //         ];

        //         $inline_kb_options = [
        //             'inline_keyboard' => $inline_keyboard
        //         ];
        //         inline_keybord($inline_kb_options , "Alright , how many words you wanna learn each day ? 🤔");

        //     }else{
        //         $message = "You have set your daily schedule before !\n Use /edit_schedule to edit your plan details . 🙃";
        //         $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
        //         $url = $GLOBALS["url"] . "/sendMessage";
        //         $response = send_reply($url, $post_params);
        //     }
        // }elseif($text === "/edit_schedule"){

        //     $result = $conn->query("SELECT * FROM schedules WHERE user_id = '$user_id' ");

        //     if($result->num_rows === 0){
        //         $message = "You haven't set your daily schedule before !\n Use /set_daily_schedule to make new one !";
        //         $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
        //         $url = $GLOBALS["url"] . "/sendMessage";
        //         $response = send_reply($url, $post_params);

        //     }else{
        //         $row = $result -> fetch_assoc();

        //         $inline_keyboard = [

        //             [
        //                 ['text' => "1 word", 'callback_data' => implode("#!", ["set_daily_schedule", 1])],
        //                 ['text' => "2 word", 'callback_data' => implode("#!", ["set_daily_schedule", 2])],
        //                 ['text' => "3 word", 'callback_data' => implode("#!", ["set_daily_schedule", 3])],

        //             ],
        //             [
        //                 ['text' => "5 word", 'callback_data' => implode("#!", ["set_daily_schedule", 4])],
        //                 ['text' => "4 word", 'callback_data' => implode("#!", ["set_daily_schedule", 5])],
        //             ],
        //         ];

        //         $inline_kb_options = [
        //             'inline_keyboard' => $inline_keyboard
        //         ];
        //         $message = "🔘 You have learnd ".$row["learned_words"]." words until now and I send you new words at<i> ".
        //         $row["time"].":00</i> every day. 😁 \n\nYou can change the number of daily words through the buttons below 👇: " ;
        //         inline_keybord($inline_kb_options , $message);  
        //     }
        // }elseif($text === "/delete_schedule"){
        //     $result = $conn->query("SELECT * FROM schedules WHERE user_id = '$user_id' ");

        //     if($result->num_rows === 0){
        //         $message = "You haven't set your daily schedule before !\n Use /set_daily_schedule to make new one !";
        //         $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
        //         $url = $GLOBALS["url"] . "/sendMessage";
        //         $response = send_reply($url, $post_params);
        //     }else{
        //         if($conn->query("DELETE FROM schedules WHERE user_id = '$user_id'  ") === TRUE){
        //             $message = "Your schedule disabled successfully !";
        //             $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
        //             $url = $GLOBALS["url"] . "/sendMessage";
        //             $response = send_reply($url, $post_params);
        //         }
        //     }
        // } 
    elseif (strpos($text, '-tr ') !== false) {

        if (substr_count($text, "-") < 2) {
            $text = str_replace("-tr ", "", $text);
            $row = $conn->query("SELECT * FROM requests WHERE user_id = '$user_id'")->fetch_assoc();
            if (date('d') == $row['day']) {
                $daily_usage = $row['daily_usage'];
                if ($daily_usage > 4000) {
                    $message = "Daily usage limit exceeded (4000 character).";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                } else {
                    $daily_usage = $daily_usage + strlen($text);
                    if ($conn->query("UPDATE requests SET daily_usage= '$daily_usage' WHERE user_id = '$user_id'  ") && $text != "") {
                        $translation = translate($text, $user_id);
                        $translated_text = $translation[0];
                        $input_lan = $translation[1];
                        if ($translated_text != '') {

                            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => "Translation :\n" . $translated_text];
                            $url = $GLOBALS["url"] . "/sendMessage";
                            $response = send_reply($url, $post_params);
                        }
                    }
                }
            } else {
                $date = date("d");
                $conn->query("UPDATE requests SET day= '$date' , daily_usage=1 WHERE user_id = '$user_id'  ");
                $translation = translate($text, $user_id);
                $translated_text = $translation[0];
                if ($translated_text != '') {

                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => "Translation :\n" . $translated_text];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                }
            }
        } else {
            $message = "Invalid input . use « -tr » to translate .";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
        }
    } elseif (strpos($text, '-pron ') !== false) {
        if (substr_count($text, "-") < 2) {
            $text = str_replace("-pron ", "", $text);
            $pronunciation_array = pronunciation($text);
            if (isset($pronunciation_array[2])) {
                $message = "I couldn't find any pronunciation for <i>" . $text . "</i> 😕";
                $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message, 'parse_mode' => "HTML"];
                $url = $GLOBALS["url"] . "/sendMessage";
                $response = send_reply($url, $post_params);
            } else {
                $file_url = $pronunciation_array[1];
                $post_params = ['chat_id' => $GLOBALS['chat_id'], 'voice' => $file_url, 'caption' => "pronunciation of " . $text];
                $url = $GLOBALS["url"] . "/sendVoice";
                $response = send_reply($url, $post_params);
            }
        } else {
            $message = "Invalid input . use « -pron » to get the pronunciation of word .";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
        }
    } elseif (strpos($text, '-def ') !== false) {
        if (substr_count($text, "-") < 2) {
            $text = str_replace("-def ", "", $text);
            $definition_array = handle_definition($text);
            if ($definition_array[1] === true) {
                $inline_keyboard = [

                    [
                        ['text' => "Add to your word list.", 'callback_data' => implode("#!", ["add_word", $text])],
                        ['text' => "Get pronunciation", 'switch_inline_query_current_chat' => "-pron " . $text],
                    ],
                ];

                $inline_kb_options = [
                    'inline_keyboard' => $inline_keyboard
                ];
                inline_keybord($inline_kb_options, "Entry word : ".$definition_array[0]);
            } else {
                $message = "I couldn't find any definition for <i>" . $text . "</i> 😕";
                $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message, 'parse_mode' => "HTML"];
                $url = $GLOBALS["url"] . "/sendMessage";
                $response = send_reply($url, $post_params);
            }
        } else {
            $message = "Invalid input . use « -def » to get the definition of word .";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
        }
    } else {
        switch ($text) {

            case '/start':

                $result = $conn->query("SELECT * FROM users WHERE user_id = '$user_id' ");

                if ($result->num_rows == 0) {
                    $conn->query("INSERT INTO users(user_id, target_lang , username , name , set_daily_word
                    )VALUES ('$user_id', '' , '$username' , '$name' , 'false' )");

                    $message = "Welcome to @transl2bot bot . Use /help to get information about bot and its usage ☺️.";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                } else {

                    $message = "Use /help to get information about bot and its usage.";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                }

                break;

            case '/set_lang':

                $inline_keyboard = [
                    [
                        ['text' => "Afrikaans", 'callback_data' => implode("#!", ["set_lang", "af", "Afrikaans"])],
                        ['text' => "Albanian", 'callback_data' => implode("#!", ["set_lang", "sq", "Albanian"])],
                        ['text' => "Amharic", 'callback_data' => implode("#!", ["set_lang", "am", "Amharic"])],

                    ],
                    [
                        ['text' => "Arabic", 'callback_data' => implode("#!", ["set_lang", "ar", "Arabic"])],
                        ['text' => "Armenian", 'callback_data' => implode("#!", ["set_lang", "hy", "Armenian"])],
                        ['text' => "Serbian (Latin)", 'callback_data' => implode("#!", ["set_lang", "sr-Latn", "Serbian (Latin)"])],

                    ],
                    [
                        ['text' => "Azerbaijani", 'callback_data' => implode("#!", ["set_lang", "az", "Azerbaijani"])],
                        ['text' => "Bangla", 'callback_data' => implode("#!", ["set_lang", "bn", "Bangla"])],
                        ['text' => "Bosnian", 'callback_data' => implode("#!", ["set_lang", "bs", "Bosnian"])],

                    ],
                    [
                        ['text' => "Bulgarian", 'callback_data' => implode("#!", ["set_lang", "bg", "Bulgarian"])],
                        ['text' => "Cantonese", 'callback_data' => implode("#!", ["set_lang", "yue", "Cantonese"])],
                        ['text' => "Catalan", 'callback_data' => implode("#!", ["set_lang", "ca", "Catalan"])],

                    ],
                    [
                        ['text' => "Chinese Simplified", 'callback_data' => implode("#!", ["set_lang", "zh-Hans", "Chinese Simplified"])],
                        ['text' => "Chinese Traditional", 'callback_data' => implode("#!", ["set_lang", "zh-Hant", "Chinese Traditional"])],
                        ['text' => "Croatian", 'callback_data' => implode("#!", ["set_lang", "hr", "Croatian"])],

                    ],
                    [
                        ['text' => "Czech", 'callback_data' => implode("#!", ["set_lang", "cs", "Czech"])],
                        ['text' => "Danish", 'callback_data' => implode("#!", ["set_lang", "da", "Danish"])],
                        ['text' => "Dari", 'callback_data' => implode("#!", ["set_lang", "prs", "Dari"])],

                    ],
                    [
                        ['text' => "Dutch", 'callback_data' => implode("#!", ["set_lang", "nl", "Dutch"])],
                        ['text' => "English", 'callback_data' => implode("#!", ["set_lang", "en", "English"])],
                        ['text' => "Estonian", 'callback_data' => implode("#!", ["set_lang", "et", "Estonian"])],

                    ],
                    [
                        ['text' => "Fijian", 'callback_data' => implode("#!", ["set_lang", "fj", "Fijian"])],
                        ['text' => "Filipino", 'callback_data' => implode("#!", ["set_lang", "fil", "Filipino"])],
                        ['text' => "Finnish", 'callback_data' => implode("#!", ["set_lang", "fi", "Finnish"])],

                    ],
                    [
                        ['text' => "French", 'callback_data' => implode("#!", ["set_lang", "fr", "French"])],
                        ['text' => "French (Canada)", 'callback_data' => implode("#!", ["set_lang", "fr-ca", "French (Canada)"])],
                        ['text' => "German", 'callback_data' => implode("#!", ["set_lang", "de", "German"])],

                    ],
                    [
                        ['text' => "Greek", 'callback_data' => implode("#!", ["set_lang", "el", "Greek"])],
                        ['text' => "Gujarati", 'callback_data' => implode("#!", ["set_lang", "gu", "Gujarati"])],
                        ['text' => "Haitian", 'callback_data' => implode("#!", ["set_lang", "ht", "Haitian"])],

                    ],
                    [
                        ['text' => "Hebrew", 'callback_data' => implode("#!", ["set_lang", "he", "Hebrew"])],
                        ['text' => "Hindi", 'callback_data' => implode("#!", ["set_lang", "hi", "Hindi"])],
                        ['text' => "Hmong Daw", 'callback_data' => implode("#!", ["set_lang", "hww", "Hmong Daw"])],

                    ],
                    [
                        ['text' => "Hungarian", 'callback_data' => implode("#!", ["set_lang", "hu", "Hungarian"])],
                        ['text' => "Icelandic", 'callback_data' => implode("#!", ["set_lang", "is", "Icelandic"])],
                        ['text' => "Indonesian", 'callback_data' => implode("#!", ["set_lang", "id", "Indonesian"])],

                    ],
                    [
                        ['text' => "Inuktitut", 'callback_data' => implode("#!", ["set_lang", "iu", "Inuktitut"])],
                        ['text' => "Irish", 'callback_data' => implode("#!", ["set_lang", "ga", "Irish"])],
                        ['text' => "Italian", 'callback_data' => implode("#!", ["set_lang", "it", "Italian"])],

                    ],
                    [
                        ['text' => "Japanese", 'callback_data' => implode("#!", ["set_lang", "ja", "Japanese"])],
                        ['text' => "Kannada", 'callback_data' => implode("#!", ["set_lang", "kn", "Kannada"])],
                        ['text' => "Kazakh", 'callback_data' => implode("#!", ["set_lang", "kk", "Kazakh"])],

                    ],
                    [
                        ['text' => "Khmer", 'callback_data' => implode("#!", ["set_lang", "km", "Khmer"])],
                        ['text' => "Korean", 'callback_data' => implode("#!", ["set_lang", "ko", "Korean"])],
                        ['text' => "Latvian", 'callback_data' => implode("#!", ["set_lang", "lv", "Latvian"])],

                    ],
                    [
                        ['text' => "Lithuanian", 'callback_data' => implode("#!", ["set_lang", "lt", "Lithuanian"])],
                        ['text' => "Malay", 'callback_data' => implode("#!", ["set_lang", "ms", "Malay"])],
                        ['text' => "Maltese", 'callback_data' => implode("#!", ["set_lang", "mt", "Maltese"])],

                    ],
                    [
                        ['text' => "Norwegian", 'callback_data' => implode("#!", ["set_lang", "nb", "Norwegian"])],
                        ['text' => "Persian", 'callback_data' => implode("#!", ["set_lang", "fa", "Persian"])],
                        ['text' => "Polish", 'callback_data' => implode("#!", ["set_lang", "pl", "Polish"])],

                    ],
                    [
                        ['text' => "Portuguese", 'callback_data' => implode("#!", ["set_lang", "pt", "Portuguese"])],
                        ['text' => "Romanian", 'callback_data' => implode("#!", ["set_lang", "ro", "Romanian"])],
                        ['text' => "Russian", 'callback_data' => implode("#!", ["set_lang", "ru", "Russian"])],

                    ],
                    [
                        ['text' => "Slovak", 'callback_data' => implode("#!", ["set_lang", "sk", "Slovak"])],
                        ['text' => "Slovenian", 'callback_data' => implode("#!", ["set_lang", "sl", "Slovenian"])],
                        ['text' => "Spanish", 'callback_data' => implode("#!", ["set_lang", "es", "Spanish"])],

                    ],
                    [
                        ['text' => "Swahili", 'callback_data' => implode("#!", ["set_lang", "sw", "Swahili"])],
                        ['text' => "Swedish", 'callback_data' => implode("#!", ["set_lang", "sv", "Swedish"])],
                        ['text' => "Turkish", 'callback_data' => implode("#!", ["set_lang", "tr", "Turkish"])],

                    ],
                    [
                        ['text' => "Ukrainian", 'callback_data' => implode("#!", ["set_lang", "uk", "Ukrainian"])],
                        ['text' => "Urdu", 'callback_data' => implode("#!", ["set_lang", "ur", "Urdu"])],
                        ['text' => "Vietnamese", 'callback_data' => implode("#!", ["set_lang", "vi", "Vietnamese"])],

                    ],

                ];
                $inline_kb_options = [
                    'inline_keyboard' => $inline_keyboard
                ];

                $json_kb = json_encode($inline_kb_options);
                $message = "Choose the language you wanna translate to :";
                $response = inline_keybord($inline_kb_options, $message);
                $result_array = json_decode($response, true);
                $msg_id  = $result_array["result"]["message_id"];

                $result = $conn->query("SELECT * FROM messages_id WHERE user_id = '$user_id' ");

                if ($result->num_rows == 0) {

                    $conn->query("INSERT INTO messages_id(user_id, msg_id)VALUES ('$user_id' , '$msg_id' )");
                } else {

                    $conn->query("UPDATE messages_id SET msg_id = '$msg_id' WHERE user_id = '$user_id' ");
                }

                break;




            case '/my_word_list':

                $result = $conn->query("SELECT * FROM users_list_words WHERE user_id = '$user_id' ");

                if ($result->num_rows == 0) {

                    $message = "Your word list is empty .";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                } else {
                    $result1 = $conn->query("SELECT * FROM users_list_words WHERE user_id = '$user_id' ");
                    $txt = "Word list :\n\n";
                    $x = 0;
                    while ($r = $result1->fetch_assoc()) {
                        $x += 1;
                        $word = $r['word'];
                        $row = $conn->query("SELECT * FROM words WHERE word= '$word' ")->fetch_assoc();
                        $id = $row['id'];
                        $str = "📌 " . $word . "\n\n❇️ Definitions : \n";
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

                        $str = $str . "\n--------------------\n";

                        $txt = $txt . $str;
                    }



                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $txt, 'parse_mode' => "HTML"];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                }
                break;

            case '/get_random_word':
                $random_url = "https://api.wordnik.com/v4/words.json/randomWord?hasDictionaryDef=true&maxCorpusCount=-1&minDictionaryCount=1&maxDictionaryCount=-1&minLength=5&maxLength=-1&api_key=" . $GLOBALS["word_api_key"];
                $cURLConnection = curl_init();
                curl_setopt($cURLConnection, CURLOPT_URL, $random_url);
                curl_setopt($cURLConnection, CURLOPT_RETURNTRANSFER, true);
                $response = curl_exec($cURLConnection);
                curl_close($cURLConnection);
                $res_array = json_decode($response, true);

                $word = $res_array["word"];
                $definition_array = handle_definition($word);
                if ($definition_array[1] === true) {
                    $inline_keyboard = [

                        [
                            ['text' => "Add to your word list.", 'callback_data' => implode("#!", ["add_word", $word])],
                            ['text' => "Get pronunciation", 'switch_inline_query_current_chat' => "-pron " . $word],
                        ],
                    ];

                    $inline_kb_options = [
                        'inline_keyboard' => $inline_keyboard
                    ];
                    inline_keybord($inline_kb_options, $definition_array[0]);
                } else {
                    $message = "I couldn't find any definition for <i>" . $word . "</i> 😕";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message, 'parse_mode' => "HTML"];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                }
                break;

            case "/help" :
                $message = "Welcome to the @Transl2bot bot.This bot is designed to translate your texts and words into over 65 living languages ​​of the world."."
                \nYou can also use this bot to access meanings , definitions , synonyms , antonym and examples of more than 800,000 English words.\n\n".
                "Usages ✅ :".
                "\n1- Translation : "."\n".
                "To translate , first you need to specify the target language . You can choose your target language by using /set_lang command.".
                "Then, all you have to do is send the text to PV for the bot. Your text should start with -tr so that the bot will underestand and translate it for you .\n\n".
                "For instance (Here the target language was set to Spanish) :\n".
                "<i>-tr If you wait, all that happens is you get older.</i>\n".
                "Output :\n".
                "<i>Translation :\n".
                "si esperas, lo único que pasa es que te haces mayor.</i>\n\n".

                "2- Definitions of English words : \n".
                "To get definition of any english word , all you need to do , is just adding -def before your word and sending it .\n\n".

                "For instance : \n".
                "<i>-def anticipate</i>\n".

                "Output :\n".

                "<i>Entry word : anticipate\n". 
                "❇️ Definitions : \n" . 
                "1 : (intransitive verb) : To see as a probable occurrence; expect.\n".
                "2 : (intransitive verb) : To think of (a future event) with pleasure; look forward to.\n" .

                "💡 Example of usage :\n" .
                "📝 What one may not so easily anticipate is the proverbial axe falling on a loved one while you stand helplessly by wishing it could be you instead of them but knowing full well that you do not get to make that choice.</i>\n\n".

                "3- Pronunciation :\n" .

                "You can also access to the pronunciation of more than 800,000 english words by sending the word with -pron .\n\n" . 

                "For instance :\n" . 
                "<i>-pron anticipat</i>\n" . 

                "Output :\n" . 
                "<i>[An audio file of pronunciation .]</i>\n\n" .

                "❕ Note that there's another way of usage for all features mentioned above which is <b>Inline-mode</b>.\n".
                "To use this feature , just type @transl2bot in your chatbox and then one of the commands like -def and finally the word you wanna know about the meaning [you don't need to send the message , just type it !] .\n\n" . 
                "For instance : \n" . 
                "if you wanna know about definition of 'anticipate' just type <i>@transl2bot -def anticipate</i>.\n\n" . 
                "In this method, there is no need to send messages in PV each time. As a result you can access the translations , definitions and pronunciations in any chat you are 🔥 .\n\n".
                "❕ You can add new words and their definitions to your word list and review them whenever you want.You can access your word list by /my_word_list command.\n". 
                "You can also delete the words from your word list by using /del command.\n\n". 
                "For instance:\n".
                "<i>/del anticipate</i> will delete 'anticipate' from your word list .

                " 
                
                ;
                $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message, 'parse_mode' => "HTML"];
                $url = $GLOBALS["url"] . "/sendMessage";
                $response = send_reply($url, $post_params);

                break ;

            default:
            $message = "Unknown request ! use /help to know about commands .";
            $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message];
            $url = $GLOBALS["url"] . "/sendMessage";
            $response = send_reply($url, $post_params);
            break;
        }
    }
}


function answer_callback_query($res_array, $callback_query_id, $user_id)
{
    $conn = connect_db();
    $command = $res_array[0];

    switch ($command) {

        case 'add_word':
            $result = $conn->query("SELECT * FROM users WHERE user_id = '$user_id' ");

            if ($result->num_rows == 0) {

                $message = "Please run /start command in bot first.\n@transl2bot";
                $url = $GLOBALS['url'] . "/answerCallbackQuery";
                $post_params = [
                    'callback_query_id' => $callback_query_id,
                    'text'              => $message,
                    'show_alert'        => true
                ];
                send_reply($url, $post_params);
            } else {
                $word = $res_array[1];
                $result = $conn->query("SELECT * FROM users_list_words WHERE user_id='$user_id' AND word='$word' ");
                if ($result->num_rows == 0) {
                    $date = strtotime('now');

                    $conn->query("INSERT INTO users_list_words (user_id , word , created_at) VALUES ('$user_id' , '$word' , '$date' ) ");
                    $message = $word . " added to your list successfully . ✅";
                    $url = $GLOBALS['url'] . "/answerCallbackQuery";
                    $post_params = [
                        'callback_query_id' => $callback_query_id,
                        'text'              => $message,
                        'show_alert'        => false
                    ];
                    send_reply($url, $post_params);
                } else {

                    $message = "This word is in your list.";
                    $url = $GLOBALS['url'] . "/answerCallbackQuery";
                    $post_params = [
                        'callback_query_id' => $callback_query_id,
                        'text'              => $message,
                        'show_alert'        => false
                    ];
                    send_reply($url, $post_params);
                }
            }
            break;

        case 'set_lang':

            $msg_id = $conn->query("SELECT * FROM messages_id WHERE user_id = '$user_id' ")->fetch_assoc()['msg_id'];

            $language = $res_array[2];
            $target_lang = $res_array[1];

            $conn->query("UPDATE users SET target_lang = '$target_lang' WHERE user_id = '$user_id' ");

            $message = "Your target language was successfully set to " . $language . " ✅ .";
            $url = $GLOBALS['url'] . "/editMessageText";
            $post_params = ['chat_id' => $user_id, 'text' => $message, 'message_id' => $msg_id];
            send_reply($url, $post_params);

            $message = "Your target language was successfully set to " . $language . " ✅";
            $url = $GLOBALS['url'] . "/answerCallbackQuery";
            $post_params = [
                'callback_query_id' => $callback_query_id,
                'text'              => $message,
                'show_alert'        => false
            ];
            send_reply($url, $post_params);
            break;

        case "set_daily_schedule":

            $words_number = $res_array[1];

            $result = $conn->query("SELECT * FROM schedules WHERE user_id = '$user_id' ");

            if ($result->num_rows === 0) {
                $time = rand(0, 24);

                if ($conn->query("INSERT INTO schedules(user_id, learned_words , time ,words_number
                )VALUES ('$user_id', 0 , '$time' , '$words_number')") === TRUE) {

                    $message = "Your daily schedule set successfully !" . " ✅";
                    $url = $GLOBALS['url'] . "/answerCallbackQuery";
                    $post_params = [
                        'callback_query_id' => $callback_query_id,
                        'text'              => $message,
                        'show_alert'        => false
                    ];
                    send_reply($url, $post_params);
                    $message = "Ok , I will send you " . $words_number .
                        " words each day with definition , pronunciation and examples of usage to learn ! 😁\n\n⏰ Time : " . $time . ":00"
                        . "\nPS: <i>Sending time is selected by the bot due to server congestion, so it's currently not possible to change this time </i>.";
                    $post_params = ['chat_id' =>  $GLOBALS['chat_id'], 'text' => $message, 'parse_mode' => "HTML"];
                    $url = $GLOBALS["url"] . "/sendMessage";
                    $response = send_reply($url, $post_params);
                }
            } else {

                $conn->query("UPDATE schedules SET words_number = '$words_number' WHERE user_id = '$user_id' ");
                $message = "Your daily schedule updated successfully !" . " ✅";
                $url = $GLOBALS['url'] . "/answerCallbackQuery";
                $post_params = [
                    'callback_query_id' => $callback_query_id,
                    'text'              => $message,
                    'show_alert'        => false
                ];
                send_reply($url, $post_params);
            }


            break;
    }
}



if (isset($update["message"])) {

    if ($chat_type == "private") {
        $text    = $update["message"]["text"];
        $user_id = $update["message"]["from"]["id"];
        $username = "@" . $update["message"]["from"]["username"];
        $name = $update["message"]["from"]["first_name"];

        if (isset($update["message"]["reply_to_message"])) {

            $text_replied = $update["message"]["reply_to_message"]["text"];

            // detect_text_received_question($text_replied , $text);

        } else {

            response($text, $user_id, $username, $name);
        }
    }
} elseif (isset($update["callback_query"])) {
    $data = $update["callback_query"]["data"];
    $callback_query_id = $update["callback_query"]["id"];
    $user_id = $update["callback_query"]["from"]["id"];
    $name = $update["callback_query"]["from"]["first_name"];
    $response = explode("#!", $data);
    answer_callback_query($response, $callback_query_id, $user_id);
} elseif (isset($update["inline_query"])) {

    $inline_query = $update["inline_query"]["query"];
    $inline_query_id = $update["inline_query"]["id"];
    $user_id = $update["inline_query"]["from"]["id"];

    handle_query($inline_query, $inline_query_id, $user_id);
} else {
    // error();
}
