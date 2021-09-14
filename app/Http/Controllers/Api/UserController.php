<?php

namespace App\Http\Controllers\Api;

use App\Repositories\ClientRepository;
use App\Repositories\UserRepository;
use Illuminate\Foundation\Auth\SendsPasswordResetEmails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Arr;
use Carbon\Carbon;


class UserController extends ApiBaseController
{
    use SendsPasswordResetEmails;

    /** @var  UserRepository */
    private $userRepository;

    /** @var  ClientRepository */
    private $clientRepository;

    public function __construct(UserRepository $userRepo, ClientRepository $clientRepo)
    {
        $this->userRepository = $userRepo;
        $this->clientRepository = $clientRepo;
    }


    /**
     * @OA\Post(
     *   path="/user/login",
     *   summary="User login,
     *   operationId="login",
     *   security={
     *      {
     *          "default": {}
     *      }
     *   },
     *   @OA\Parameter(
     *     name="email",
     *     in="query",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="password",
     *     in="query",
     *     required=true,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Parameter(
     *     name="device_token",
     *     in="query",
     *     required=false,
     *     @OA\Schema(type="string")
     *   ),
     *   @OA\Response(response=200, description="successful "),
     *   @OA\Response(response=401, description="missing data or Unauthorized"),
     *   @OA\Response(response=404, description="request not found"),
     *   @OA\Response(response=405, description="Method Not Allowed"),
     *   @OA\Response(response=500, description="internal server error")
     * )
     */
    function login(Request $request)
    {
        //check request is not empty
        $valid = validator($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($valid->fails()) {
            return $this->sendError($valid->errors()->first(), 406);
        }

        if (auth()->attempt(['email' => $request->input('email'), 'password' => $request->input('password')]))
        {
            return $this->sendResponse(auth()->user(), 'User retrieved successfully');
        }

        return $this->sendError('Unauthenticated user', 401);
    }


    /**
     * Create a new user instance after a valid registration.
     *
     * @param array $data
     * @return
     */
    function register(Request $request)
    {
        //check request is not empty
        $valid = validator($request->all(), [
            'first_name' => 'required|min:3',
            'last_name' => 'required|min:3',
            'phone' => 'required|digits_between:10,12|unique:users,phone',
            'email' => 'required|unique:users,email',
            'password' => 'required|confirmed|min:6',
            'latitude' => 'required',
            'longitude' => 'required',
            'address1' => 'required',
            'city' => 'required',
            'state' => 'required',
            'country' => 'required',
            'zip' => 'required',
            'phone_no1' => 'required|digits_between:10,12',
        ]);

        if ($valid->fails()) {
            return $this->sendError($valid->errors()->first(), 406);
        }

        $userData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'phone' => $request->phone,
            'last_password_reset' => Carbon::now(),
            'status' => 'active',
            'password' => Hash::make($request->password)
        ];

        $user = $this->userRepository->create($userData);

        $clientData = [
            'user_id' => $user->id,
            'client_name' => $request->first_name.' '. $request->last_name,
            'address1' => $request->address1,
            'address2' => $request->address2,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'city' => $request->city,
            'state' => $request->state,
            'country' => $request->country,
            'zip' => $request->zip,
            'phone_no1' => $request->phone_no1,
            'phone_no2' => $request->phone_no2,
            'start_validity' => Carbon::now(),
            'end_validity' => Carbon::now()->addDays(30),
            'status' => 'active',
        ];

        $client = $this->clientRepository->create($clientData);
        return $this->sendResponse($user, 'User retrieved successfully');
    }

    function logout(Request $request)
    {
        $request->user()->token()->revoke();

        return response()->json([
            'success' => true,
            'message' => 'You have been successfully logged out!',
        ], 200);
    }

    /**
     * @OA\Post(
     *   path="/user",
     *   summary="User data,
     *   operationId="user",
     *   security={
     *      {
     *          "bearerAuth": {}
     *      }
     *   },
     *
     *   @OA\Response(response=200, description="successful "),
     *   @OA\Response(response=401, description="missing data or Unauthorized"),
     *   @OA\Response(response=404, description="request not found"),
     *   @OA\Response(response=405, description="Method Not Allowed"),
     *   @OA\Response(response=500, description="internal server error")
     * )
     */
    function user(Request $request)
    {
        $user = auth()->user();

        if (!$user) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }

        return $this->sendResponse($user, 'User retrieved successfully');
    }

    /**
     * Update the specified User in storage.
     *
     * @param int $id
     * @param Request $request
     *
     * @return Response
     */
    public function update($id, Request $request)
    {
        $user = $this->userRepository->findWithoutFail($id);

        if (empty($user)) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], 'User not found');
        }
        $input = $request->except(['password']);
        try {

            $user = $this->userRepository->update($input, $id);

        } catch (ValidatorException $e) {
            return $this->sendResponse([
                'error' => true,
                'code' => 404,
            ], $e->getMessage());
        }

        return $this->sendResponse($user, __('lang.updated_successfully', ['operator' => __('lang.user')]));
    }

    function sendResetLinkEmail(Request $request)
    {
        $this->validate($request, ['email' => 'required|email']);

        $response = $this->broker()->sendResetLink(
            $request->only('email')
        );

        if ($response == Password::RESET_LINK_SENT) {
            return $this->sendResponse(true, 'Reset link was sent successfully');
        } else {
            return $this->sendError([
                'error' => 'Reset link not sent',
                'code' => 401,
            ], 'Reset link not sent');
        }
    }
}
