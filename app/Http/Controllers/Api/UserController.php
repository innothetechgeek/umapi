<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Auth;

class UserController extends Controller
{
    //

    private $token_name = 'active_user_token';


    public function register(Request $request){

        $user_data = $request->all();
        
        $user_password = Str::random(8);
        $user_data['password'] = Hash::make($user_password);

        $user = User::create(
            $user_data 
        );

        $token = $user->createToken($this->token_name);

        return [
            'token' => $token,
            'password' => $user_password,
        ];

    }

    public function login(Request $request){

        $user = User::where('email', $request->username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response('Login invalid', 503);
        }

        return [
            'token' => $user->createToken($this->token_name),
            'user' => $this->retrieveUserImages(),
        ];
      

    }

    public function uploadImage(Request $request){


        $token = $request->bearerToken();

        //retrieve user based on the token provided
        $active_user = auth('sanctum')->user();

        $active_user_id = $active_user->id; //get user id

        $fileName = time().'_'.$request->file->getClientOriginalName();
        
        //store the file in user azure storage disk
        $request->file('file')->storeAs("user_images/user_$active_user_id", $fileName, 'azure');


    }

    public function retrieveUserImages(){

        //azure storage disk
         $disk = \Storage::disk('azure');

         $optedInUsers = User::where('OptIn',1)->get();


      
         $users_arr = array();

         foreach($optedInUsers as $user){

            $user_id =  $user->id;

            //array to hold user image links
            $user_images = array();

            //retrieve user files from azure storage disk
            $files = $disk->files("user_images/user_$user_id");
            
            if(count( $files )){
               
                foreach($files as $file) {
                    
                    $azure_storage_url = config('app.azure_storage_url');
                    $image_link =  $azure_storage_url."/".$file;

                    //store image link in users image array
                    array_push($user_images,$image_link);
                }

                $user->images = $user_images;
                //store user object in the users array
                array_push($users_arr, $user);

            }
           
         }
         
         $results = json_encode($users_arr, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
         
         //returns response to the frontend
         return response($results)->header('content-type', 'application/json');
 

    }
}
