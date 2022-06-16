<?php

namespace App\Api\V1\Controllers;

use Sumra\SDK\JsonApiResponse;
use Illuminate\Http\Request;
use App\Models\BotDetail;
use App\Models\Channel;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\Rule;

/**
 * Class ChatController
 *
 * @package App\Api\V1\Controllers
 */
class ChatBotController extends Controller
{
    /**
     * @param Chat $botdetail
     */
    private BotDetail $botdetail;

    /**
     * ChatController constructor.
     *
     * @param Chat $botdetail
     */
    public function __construct(BotDetail $botdetail)
    {
        $this->botdetail = $botdetail;
        $this->user_id = auth()->user()->getAuthIdentifier();
    }


    /**
     * Save influential bot detail
     *
     * @OA\Post(
     *     path="/bot-details",
     *     summary="Save influential bot detail",
     *     description="Save influential bot detail",
     *     tags={"Bots"},
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
     *         @OA\JsonContent(ref="#/components/schemas/BotDetailSchema")
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Successfully save"
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="Chat created"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized"
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
     *         response="422",
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'name' => ["required","string", Rule::in(Channel::$platforms)],
            'token' => 'required|string',
            'type' => 'required|string',
        ]);
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }

        // Try to add new chat
        try {

            // create new chat 
            $chat = $this->botdetail->create([
                'user_id' => $this->user_id,
                'type' => $request->get('type'),
                'token' => $request->get('token'),
                'name' => $request->get('name')
            ]);

            // Return response to client
            return response()->jsonApi([
                'type' => 'success',
                'title' => 'New bot detail created registration',
                'message' => "Chat successfully added",
                'data' => $chat->toArray()
            ], 200);
        } catch (Exception $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => 'New chat registration',
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }


    /**
     * Update a chat
     *
     * @OA\Put(
     *     path="/chats/{id}",
     *     summary="Update a chat",
     *     description="Update a chat",
     *     tags={"Chats"},
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
     *     @OA\Parameter(
     *         name="chat_id",
     *         in="path",
     *         description="chat Id",
     *         example="0aa06e6b-35de-3235-b925-b0c43f8f7c75",
     *         required=true,
     *         @OA\Schema(
     *             type="string"
     *         )
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *              @OA\Property(
     *                  property="status",
     *                  type="string",
     *                  default="delivered",
     *                  description="Could be delivered/seen/deleted"
     *              ),
     *          )
     *     ),
     *     @OA\Response(
     *         response="200",
     *         description="Successfully save"
     *     ),
     *     @OA\Response(
     *         response="201",
     *         description="Chat created"
     *     ),
     *     @OA\Response(
     *         response="400",
     *         description="Invalid request"
     *     ),
     *     @OA\Response(
     *         response="401",
     *         description="Unauthorized"
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
     *         response="422",
     *         description="Validation failed"
     *     ),
     *     @OA\Response(
     *         response="500",
     *         description="Internal server error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        // Validate input
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:delivered,seen,deleted',
        ]);
        if ($validator->fails()) {
            throw new Exception($validator->errors()->first());
        }

        // Try to update chat
        try {

            // find chat with id
            $chat = $this->getObject($id);

            if ($chat instanceof JsonApiResponse) {
                return $chat;
            }

                $status = $request->get('status');
                if ($status == "delivered") {
                    $chat->update([
                        'is_delivered' => 1,
                    ]);
                } else if ($status == "seen") {
                    $chat->update([
                        'is_seen' => 1,
                    ]);
                } else if ($status == "deleted") {
                    if ($chat->user_id == $this->user_id) {
                        $chat->update([
                            'deleted_from_sender' => 1
                        ]);
                    } else {
                        $chat->update([
                            'deleted_from_receiver' => 1
                        ]);
                    }
                }

            // Return response to client
            return response()->jsonApi([
                'type' => 'success',
                'title' => 'Chat updation',
                'message' => "Chat successfully updated",
                'data' => $chat->toArray()
            ], 200);
        } catch (Exception $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => 'Chat updation',
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    /**
     * Get chat object
     *
     * @param $id
     * @return mixed
     */
    private function getObject($id): mixed
    {
        try {
            return $this->botdetail::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return response()->jsonApi([
                'type' => 'danger',
                'title' => "Get chat",
                'message' => "Chat with id #{$id} not found: {$e->getMessage()}",
                'data' => ''
            ], 404);
        }
    }
}
