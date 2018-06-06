<?php

namespace App\Http\Controllers\Auth;

use App\User;
use Validator;
use Authorizer;
use Response;
use Auth;
use Redirect;
use Session;
use Hash;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\ThrottlesLogins;
use Illuminate\Foundation\Auth\AuthenticatesAndRegistersUsers;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Models\OAuth\OAuthClient;
use Illuminate\Support\Facades\Input;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\BaseController;
use App\Models\Users\UserRole;

class AuthController extends BaseController
{
    /*
    |--------------------------------------------------------------------------
    | Registration & Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users, as well as the
    | authentication of existing users. By default, this controller uses
    | a simple trait to add these behaviors. Why don't you explore it?
    |
    */

    use AuthenticatesAndRegistersUsers, ThrottlesLogins;

    /**
     * Where to redirect users after login / registration.
     *
     * @var string
     */
    protected $redirectTo = '/manage-item-ajax';

    /**
     * Create a new authentication controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware($this->guestMiddleware(), ['except' => 'logout']);
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => 'required|max:255',
            'email' => 'required|email|max:255|unique:users',
            'password' => 'required|min:6|confirmed',
        ]);
    }

    public function index()
    {
         $user_role = UserRole::all();
         return view('welcome')->with('userrole',$user_role);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return User
     */
    protected function create(array $data)
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }     
     

    public function login(Request $request)
    {
        $rules = array (
                
                'username' => 'required',
                'password' => 'required' 
        );
        $validator = Validator::make ( Input::all (), $rules );
        if ($validator->fails ()) {
            return Redirect::back ()->withErrors ( $validator, 'login' )->withInput ();
        }
        else 
        {
             $clientData=DB::table('oauth_clients')->where('name', 'api')->first();
        $userScope=$this->checkUserScope(Input::get('username'));
        Input::merge([
                'grant_type'    => "password",
                'client_id'     => "".$clientData->id,
                'client_secret' => "".$clientData->secret,
                'scope'         => $userScope
            ]);
            $credentials = $request->only(['grant_type', 'client_id', 'client_secret', 'username', 'password','scope']);

            $credentials["client_id"]="".$clientData->id;
            $credentials["client_secret"]="".$clientData->secret;
            try {
                if (! $accessToken = Authorizer::issueAccessToken()) {
                    return Redirect::back ()->withErrors ( $this->response->errorUnauthorized(), 'login' )->withInput (); 
                }
            }
            catch (\League\OAuth2\Server\Exception\OAuthException $e)
            {
                throw $e;
                return Redirect::back ()->withErrors ('could_not_create_token' , 'login' )->withInput ();
            }
            $accessToken["groups"]=$userScope;
            $request->headers->set('Authorization','Bearer '.$accessToken['access_token']);
            Authorizer::validateAccessToken();
            $userId = Authorizer::getResourceOwnerId();
            //$userType=User::find($userId)->id;
            //$accessToken['userable_id']=$userType;
            $accessToken['userId']=$userId;
        }
        //$data = "";
        //$accesstoken = 
            //return $accessToken;   
            return response()->json(compact('accessToken'));
            // return response()
            // ->view('manage-item-ajax', $accessToken, 200)
            // ->header('Authorization','Bearer '.$accessToken['access_token']);
              // return redirect(asset('manage-item-ajax'), $status = 301, $headers = ['Authorization','Bearer '.$accessToken['access_token']]);
            //return Redirect::to('manage-item-ajax')->with(compact('accessToken'));
            //return      response()->json();
    }

    public function getUserIdByEmail($email)
    {
        try
        {
            $user=User::where('email',$email)->firstOrFail();
            return $user;
        }
        catch(ModelNotFoundException $mnfex)
        {
            return response()->error('User Does Not Exists !', 404);
        }
        catch(\Exception $ex)
        {
            return response()->error('Error Occurred !', 500);
        }
    }

    public function checkUserScope($username)
    {
        try
        {
            if((User::where('email', '=', $username)->exists()))
            {
               $userId=User::where('email', '=', $username)->pluck('id');
//                $user=User::find($userId);
//                $groups=$user->groups;
//
//                return $groups[0]->name;
                $user=DB::table('users')
                    ->select('user_role')
                    ->join('user_roles','user_roles.id','=','users.user_role_id')
                    ->where('users.id','=',$userId)
                    ->get();

                //dd($user);
                return $user[0]->user_role;
            }
            else
            {
                return "empty";
            }
        }
        catch(\Exception $ex)
        {
            return response()->error('Error Occurred : '.$ex->getMessage(), 404);
        }
    }

        public function register(Request $request) {
        $rules = array (
                'email' => 'required|unique:users|email',
                'name' => 'required|unique:users|alpha_num|min:4',
                'password' => 'required|min:6|confirmed',
                'user_role_id' =>'required|exists:user_roles,id' 
        );
        $validator = Validator::make ( Input::all (), $rules );
        if ($validator->fails ()) {
            return Redirect::back ()->withErrors ( $validator, 'register' )->withInput ();
        } else {
            
            $user = new User ();
            $user->name = $request->get ( 'name' );
            $user->email = $request->get ( 'email' );
            $user->user_role_id = $request->get ( 'user_role_id' );
            $user->password = Hash::make ( $request->get ( 'password' ) );            
            
            $user->save ();
            return Redirect::back ();
        }
    }
    public function logout() 
    {
        Session::flush ();
        Auth::logout ();
        return Redirect::back ();
    }

}
