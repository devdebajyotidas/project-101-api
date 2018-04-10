<?php

namespace App\Http\Controllers\API;

use App\Models\Account;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{

    function index($id){
        $response=new \stdClass();

        if(isset($id) && !empty($id)){
            $services=Service::where('account_id',$id)->where('is_active','1')->get();
            $response->status=true;
            $response->data=$services;
            $response->message="Services found";
            $http=Response::HTTP_OK;
        }
        else{
            $response->status=false;
            $response->data=array();
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);

    }

    /*service details*/
    /*This in incomplete...will be updated after future changes*/
    function show($userId,$serviceId){
        $response=new \stdClass();
        if(isset($userId) && !empty($userId) && isset($serviceId) && !empty($serviceId)){
            $service=Service::with('account.user')->where('id',$serviceId)->first();
            $account=$service->account;
            $cord=$this->getUserLocation($userId);

            $distance=$this->getDistanceBetweenPointsNew($cord->latitude, $cord->longitude, $account->latitude, $account->longitude, $unit = 'Km');

            $data=array(
                'provider'=>$service->account->user->name,
                'provider_id'=>$account->id,
                'name'=>$service->name,
                'service_id'=>$service->id,
                'address'=>$this->addressFormat($account->address,$account->city,$account->state,$account->country,$account->zip),
                'ratings'=>0,
                'distance'=>$distance." Km"
            );
            $response->status=true;
            $response->data= $data;
            $response->message="Service found";
            $http=Response::HTTP_OK;
        }
        else{
            $response->status=false;
            $response->data=array();
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

    function addressFormat($address,$city,$state,$country,$zip){
        $formatted=null;

        if(!empty($address))
            $formatted.=$address;

        if(!empty($city))
            $formatted.=' ,'.$city;

        if(!empty($state))
            $formatted.=' ,'.$state;

        if(!empty($country))
            $formatted.=' ,'.$country;

        if(!empty($zip))
            $formatted.=', '.$zip;

        return ltrim(preg_replace('/,/', '', $formatted, 1));


    }

    /*Store Services*/
    function store(Request $request,$id){
        $response=new \stdClass();

        DB::beginTransaction();

        $names=$request->name;
        $count=0;
        $service['account_id']=$id;

        try{
            foreach ($names as $name){
                $exist=Service::where('name',$name)->where('account_id',$id)->exists();
                if(!$exist){
                    $service['name']=$name;
                    if(Service::create($service)){
                        $count++;
                    }
                }
                else{
                    $count++;
                }
            }
        }catch (\Exception $exception){
            $response->status=false;
            $response->message=$exception->getMessage();
            $http=Response::HTTP_BAD_REQUEST;
            return response()->json($response,$http);
        }

        if($count > 0){
            DB::commit();
           $response->status=true;
           $response->message="Services has been added";
           $http=Response::HTTP_CREATED;
        }
        else{
            DB::rollBack();
            $response->status=false;
            $response->message="Unable to add the services";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

    /*update service*/
    function update(Request $request,$id){
        $response=new \stdClass();

        DB::beginTransaction();

        if(isset($id) && !empty($id)){
            try{
                $data=$request->all();
                if(empty($data)){
                    $response->status=false;
                    $response->message="No data to update";
                    $http=Response::HTTP_OK;
                }
                else{
                    $service=Service::find($id);
                    $update=$service->update($data);
                    if($update){
                        DB::commit();
                        $response->status=true;
                        $response->message="Service has been updated";
                        $http=Response::HTTP_CREATED;
                    }
                    else{
                        DB::rollBack();
                        $response->status=false;
                        $response->message="Unable to update the service";
                        $http=Response::HTTP_OK;
                    }
                }
            }catch (\Exception $exception){
                $response->status=false;
                $response->message=$exception->getMessage();
                $http=Response::HTTP_BAD_REQUEST;
                return response()->json($response,$http);
            }

        }
        else{
            $response->status=false;
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

    /*remove service*/
    function delete($id){
        $response=new \stdClass();

        DB::beginTransaction();

        if(isset($id) && !empty($id)){
            $service=Service::find($id);
            $delete=$service->delete();
            if($delete){
                DB::commit();
                $response->status=true;
                $response->message="Service has been removed";
                $http=Response::HTTP_CREATED;
            }
            else{
                DB::rollBack();
                $response->status=false;
                $response->message="Unable to remove the service";
                $http=Response::HTTP_OK;
            }
        }
        else{
            $response->status=false;
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

    /*service by location*/
    function serviceByLocation($id){
        $response=new \stdClass();

        if(isset($id) && !empty($id)){

            $coordinates=$this->getUserLocation($id);

            $lat = $coordinates->latitude;
            $lon = $coordinates->longitude;
            $radius = 1; // Km

            $angle_radius = $radius / ( 111 * cos( $lat ) ); // Every lat|lon degree° is ~ 111Km
            $min_lat = $lat - $angle_radius;
            $max_lat = $lat + $angle_radius;
            $min_lon = $lon - $angle_radius;
            $max_lon = $lon + $angle_radius;

            $results=Account::whereBetween('longitude', array($min_lon, $max_lon))->whereBetween('latitude', array($min_lat, $max_lat))->where('is_provider','1')->get();
            $n_rows = count( $results);
            for($i=0; $i<$n_rows; $i++) {
                if($this->getDistanceBetweenPointsNew($lat, $lon, $results[$i]->latitude, $results[$i]->longitude, 'Km') > $radius) {
                    // This is out of the "perfect" circle radius. Strip it out.
                    unset($results[$i]);
                }
            }

            $ids=$results->pluck('id')->toArray();
            $services=Service::whereIn('account_id',$ids)->where('is_active','1')->get();
            $response->status=true;
            $response->data=$services;
            $response->message="Service found";
            $http=Response::HTTP_OK;
        }
        else{
            $response->status=false;
            $response->data=array();
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

    function getDistanceBetweenPointsNew($latitude1, $longitude1, $latitude2, $longitude2, $unit = 'Km')
    {
        $theta = $longitude1 - $longitude2;
        $distance = (sin(deg2rad($latitude1)) * sin(deg2rad($latitude2))+
            (cos(deg2rad($latitude1)) * cos(deg2rad($latitude2)) * cos(deg2rad($theta))));
        $distance = acos($distance); $distance = rad2deg($distance);
        $distance = $distance * 60 * 1.1515;

        switch($unit)
        {
            case 'Mi': break;
            case 'Km' : $distance = $distance * 1.609344;
        }
        return (round($distance,2));
    }

    function getUserLocation($id){
        $result=Account::where('id',$id)->first(['longitude','latitude']);
        return $result;
    }

    /*service by provider*/
    function serviceByProvider($provider){
        $response=new \stdClass();

        if(isset($provider) && !empty($provider)){
            $services=Service::where('account_id',$provider)->where('is_active','1')->get();
            $response->status=true;
            $response->data=$services;
            $response->message="Services found";
            $http=Response::HTTP_OK;
        }
        else{
            $response->status=false;
            $response->data=array();
            $response->message="Please provide a valid account";
            $http=Response::HTTP_OK;
        }

        return response()->json($response,$http);
    }

}
