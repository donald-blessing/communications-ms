<?php

namespace App\Api\V1\Controllers;

use App\Models\BotConversation;
use App\Models\BotChat;
use Sumra\SDK\JsonApiResponse;
use Illuminate\Http\Request;
use App\Models\BotDetail;
use App\Models\Channel;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

/**
 * Class BotMessageController
 *
 * @package App\Api\V1\Controllers 
 */
class BotMessageController extends Controller
{

    private BotDetail $botdetail;
    private BotConversation $botconversation;
    private BotChat $botchat;

    /**
     * BotMessageController constructor.
     *
     * @param BotDetail $botdetail
     * @param BotConversation $botconversation
     * @param BotChat $botchat
     */
    public function __construct(BotDetail $botdetail, BotConversation $botconversation, BotChat $botchat)
    {
        $this->botdetail = $botdetail;
        $this->botconversation = $botconversation;
        $this->botchat = $botchat;
        $this->user_id = auth()->user()->getAuthIdentifier();
    }


    /**
     * Send Message to external user.
     *
     * @OA\Post(
     *     path="/send",
     *     summary="Send Message to external user",
     *     description="Send Message to external user",
     *     tags={"Bot Messages"},
     *
     *     security={{
     *         "default": {
     *             "ManagerRead",
     *             "User",
     *             "ManagerWrite"
     *         }
     *     }},
     *     x={
     *         "auth-type": "Application & Application User",
     *         "throttling-tier": "Unlimited",
     *         "wso2-application-security": {
     *             "security-types": {"oauth2"},
     *             "optional": "false"
     *         }
     *     },
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *     @OA\Property(
     *         property="text",
     *         type="string",
     *         description="message to be sent",
     *         example="Hey, how are you doing?"
     *     ),
     *     @OA\Property(
     *         property="chat_id",
     *         type="string",
     *         description="Chat Id of the user to send a message",
     *         example="2063523844"
     *     ),
     *          )
     *     ),
     *
     *     @OA\Response(
     *         response="200",
     *         description="Success send data"
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *     @OA\Response(
     *         response="403",
     *         description="Forbidden"
     *     ),
     *     @OA\Response(
     *         response="404",
     *         description="Not found"
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error"
     *     )
     * )
     */
    public function sendMessage(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'type' => ["required", "string", Rule::in(Channel::$types)],
            'text' => 'required|string',
            'chat_id' => 'required|string',
        ]);
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }

        // Try to add new botdetail
        try {
            // check if same bot detail has already been created
            $botdetail = $this->botdetail->where(['user_id' => $this->user_id, 'type' => $request->get('type')])->first();

            if (!$botdetail) {
                return response()->jsonApi([
                    'type' => 'danger',
                    'title' => 'Send Message',
                    'message' => "User has not created a bot for {$request->get('type')}",
                    'data' => null
                ], 400);
            }

            if ($request->get('type') == "telegram") {
                // call telegram bot api 
                $client = new \GuzzleHttp\Client();
                $response = $client->request('POST', "https://api.telegram.org/bot{$botdetail->token}/sendMessage", [
                    'form_params' => [
                        'chat_id' => $request->get('chat_id'),
                        'text' => $request->get('text'),
                    ]
                ]);

                $data = json_decode($response->getBody(), true);
                if ($data['ok'] == true) {
                    // save bot chat and conversation
                    $this->saveBotChats($data['result'], $this->user_id);
                }
            }

            // Return response
            return response()->jsonApi([
                'type' => 'success',
                'title' => "send message",
                'message' => 'Your message has been sent',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => "send message",
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function saveUpdates(Request $request, $type, $token)
    {
        // Try to save updates sent from bot
        try {

            if ($type == "telegram") {
                // call telegram bot api 
                if ($request->has('update_id')) {
                    // save bot chat and conversation
                    $data = $request->get('message');
                    // add token to the message array 
                    $new_data = array_merge($data, [
                        'token' => $token
                    ]);
                    $this->saveBotChats($new_data);
                }
            }

            \Log::info("Update has been saved");

            // Return response
            return response()->jsonApi([
                'type' => 'success',
                'title' => "send message",
                'message' => 'Your message has been sent',
                'data' => $data
            ], 200);
        } catch (Exception $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => "send message",
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    public function saveBotChats($data, $user_id = null)
    {
        $bot_username = $data['from']['username'];
        $chat_id = $data['chat']['id'];

        $botconversation = $this->botconversation->where(['bot_username' => $bot_username, 'chat_id' => $chat_id])->first();

        // if botconversation does not exist, create it
        if (!$botconversation) {
            // if user_id is null, get it using the token
            if (!$user_id) {
                $user_id = $this->botdetail->where('token', $data['token'])->first()->user_id;
            }
            $botconversation = $this->botconversation->create([
                'user_id' => $user_id,
                'bot_name' => $data['from']['first_name'],
                'bot_username' => $data['from']['username'],
                'chat_id' => $chat_id,
                'first_name' => $data['chat']['first_name'],
                'last_name' => $data['chat']['last_name']
            ]);
        }

        // check whether message is replying to another message
        if (array_key_exists("reply_to_message", $data)) {
            $replied_to_message_id = $data['reply_to_message']['message_id'];
        } else {
            $replied_to_message_id = null;
        }

        // save bot chat
        $this->botchat->create([
            'message_id' => $data['message_id'],
            'date' => $data['date'],
            'text' => $data['text'],
            'replied_to_message_id' => $replied_to_message_id,
            'bot_conversation_id' => $botconversation->id
        ]);
    }
}
