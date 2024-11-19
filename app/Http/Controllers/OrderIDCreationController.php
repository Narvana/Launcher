<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\OrderDetailMail;
use Illuminate\Http\Request;
use App\Models\OrderIDCreation;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderIDCreationController extends Controller
{
    //

    /**
     * @group Order
     * 
     * Add a new order.
     *
     * This endpoint is used to create a new order.
     * 
     * **Note:** This endpoint requires an `Authorization: Bearer <access_token>` header.
     * 
     * **Note:** You will get the access_token after User Login
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {access_token}
     * 
     * @bodyParam OrderDetails array required The details of the order.
     * @response 201 {
     *   "success": 1,
     *   "order": {
     *     "id": 1,
     *     "user_id": 1,
     *     "OrderDetails": "{\"item\":\"product\"}",
     *     "OrderID": "launcherr123ABC",
     *     "Status": "Order Created",
     *     "created_at": "2024-10-29T00:00:00.000000Z",
     *     "updated_at": "2024-10-29T00:00:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "success": 0,
     *   "error": "The OrderDetails field is required."
     * }
     * 
     * @response 500 {
     *   "success": 0,
     *   "message" : 'Order Creation Failure', 
     *   "error" : 'Internal Serve Error'
     * }
     * 
     */

    public function AddOrderID(Request $request)
    {
        $validator=Validator::make($request->all(),[
        'OrderDetails' => 'required|array',     
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->first(); // Get all error messages
            return response()->json([
                'success' => 0,
                'error' => $error
            ], 422);
        }

        try {
            //code...
            $timestamp = time(); // Current timestamp
            $orderID = 'launcherr' . Str::upper(substr(md5($timestamp), 0, 12)); // Generate 12 characters from MD5 hash
    
            $order = OrderIDCreation::create([
                'user_id' => Auth()->guard()->id(),
                'OrderDetails' => json_encode($request->OrderDetails),
                'OrderID' => $orderID,
                'Status' => 'Order Created'
            ]);
            return response()->json(['success' => 1, 'order' => $order], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0, 
                'message' => 'Order Creation Failure', 
                'error' => $e->getMessage()], 
                500);
        }
    }


    /**
     * 
     * @group Order 
     * 
     * Update Order status.
     *
     * This endpoint allows updating the status of an existing order.
     * 
     * **Note:** This endpoint requires an `Authorization: Bearer <access_token>` header.
     *
     * **Note:** You will get the access_token after User Login
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {access_token}
     * 
     * @bodyParam OrderID string required The unique identifier for the order. Example: launcherr123ABC
     * @bodyParam OrderStatus string required The new status of the order. Example: Delivered
     * @response 200 {
     *   "success": 1,
     *   "message": "Status Updated"
     * }
     * @response 400 {
     *   "success": 0,
     *   "message": "No Order Found"
     * }
     * 
     * @response 500 {
     *   "success": 0,
     *   "message" : 'An error occurred Update Order Status', 
     *   "error" : 'Internal Serve Error'
     * 
     */

    public function UpdateOrderStatus(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'OrderID' => 'required|string',
            'OrderStatus' => 'required|string',     
        ]);

        if ($validator->fails()) {
            $error = $validator->errors()->first(); // Get all error messages
            return response()->json([
                'success' => 0,
                'error' => $error
            ], 422);
        }

        try {
            //code...
            $data=$validator->validated();
            $order = OrderIDCreation::where('OrderID',$data['OrderID'])->first();

            if(!$order)
            {
                return response()->json([
                    'success' => 0,
                    'message' => 'No Order Found',
                ], 400);
            }            
            $order->update([
                'Status' => $data['OrderStatus']
            ]);

            $user=Auth()->guard('api')->user();

            $OrderDetails=json_decode($order->OrderDetails,true);

            // return response()->json($OrderDetails);

            if($data['OrderStatus'] === 'PaymentSuccess')
            {
             Mail::to($user->email)->send(new OrderDetailMail($OrderDetails));               
            }
       
            return response()->json([
                'success' => 1,
                'message' => 'Status Updated'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => 'An error occurred Update Order Status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    /**
     * @group Order
     * 
     * Get orders of the logged-in user.
     *
     * This endpoint retrieves all orders placed by the authenticated user.
     * 
     * **Note:** This endpoint requires an `Authorization: Bearer <access_token>` header.
     *
     * **Note:** You will get the access_token after User Login
     * 
     * @authenticated
     * 
     * @header Authorization Bearer {access_token}
     *
     * @response 200 {
     *   "success": 1,
     *   "message": "Order List for John Doe",
     *   "data": [
     *     {
     *       "id": 1,
     *       "OrderID": "launcherr123ABC",
     *       "OrderDetails": {
     *         "item": "product"
     *       },
     *       "OrderStatus": "Order Created"
     *     }
     *   ]
     * }
     * @response 400 {
     *   "success": 0,
     *   "message": "No Order Found for John Doe"
     * }
     */


    public function GetOrderUser(Request $request)
    {
        $tokenType = $request->attributes->get('token_type');

        if ($tokenType === 'public') {
            return response()->json(
                [
                    'success'=> 0,
                    'message' => 'Unauthorized, Login To Add Enquiry'
                ]
            );
        }
        else if ($tokenType === 'user') {
            $user = $request->attributes->get('user');

            $order= OrderIDCreation::where('user_id',$user->id)->get();
    
            if($order->isEmpty())
            {
                return response()->json(['success'=>0, 'message' => "No Order Found for {$user->name}"],400);
            }
            $data=[];
            foreach($order as $Order)
            {
                $final = [
                    'id'=>$Order->id,
                    'OrderID' => $Order->OrderID,
                    'OrderDetails' => json_decode($Order->OrderDetails,true),
                    'OrderStatus' => $Order->Status,
                ];
                array_push($data,$final);
            }
            return response()->json(
                [
                    'success'=>1, 
                    'message' => "{$user->name} Order List", 
                    'data' => $data
                ],200);    
        }


    }

    /**
     * Get details of an order.
     *
     * This endpoint retrieves the details of a specific order using its OrderID.
     *
     * @bodyParam OrderID string required The unique identifier for the order. Example: launcherr123ABC
     * @response 200 {
     *   "success": 1,
     *   "message": "Order Detail",
     *   "data": {
     *     "OrderStatus": "Order Created",
     *     "OrderDetails": {
     *       "item": "product"
     *     }
     *   }
     * }
     * @response 400 {
     *   "success": 0,
     *   "message": "No Order Detail Found"
     * }
     */

    public function GetOrderDetails(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'OrderID' => 'required|string',
            ]);
    
        if ($validator->fails()) {
            $error = $validator->errors()->first(); // Get all error messages
            return response()->json([
                'success' => 0,
                'error' => $error
            ], 422);
        }
        try {
            //code...

            $data=$validator->validated();

            $order=OrderIDCreation::where('OrderID',$data['OrderID'])->first();

            if(!$order)
            {
                return response()->json(['success' => 0,'message' => 'No Order Detail Found'],400);
            }
            return response()->json(
                [
                    'success' => 1,
                    'message' => 'Order Detail',
                    'data' => [
                        "OrderStatus" => $order->status,
                        "OrderDetails"=>json_decode($order->OrderDetails,true)
                    ]
                 ],200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0, 
                'message' => 'Order Failure while Retreating', 
                'error' => $e->getMessage()], 
                500);
        }

    }



    /**
     * Get all orders.
     *
     * This endpoint retrieves a list of all orders in the system.
     *
     * @response 200 {
     *   "success": 1,
     *   "message": "Order List",
     *   "data": [
     *     {
     *       "id": 1,
     *       "OrderID": "launcherr123ABC",
     *       "OrderStatus": "Order Created",
     *       "OrderDetails": {
     *         "item": "product"
     *       }
     *     }
     *   ]
     * }
     * @response 400 {
     *   "success": 0,
     *   "message": "No Order Found"
     * }
     */
    public function GetAllOrders(Request $request)
    {
        $order= OrderIDCreation::get();

        if($order->isEmpty())
        {
            return response()->json(['success'=>0, 'message' => 'No Order Found'],400);
        }
        $data=[];
        foreach($order as $Order)
        {
            $final = [
                'id'=>$Order->id,
                'OrderID' => $Order->OrderID,
                'OrderStatus' => $Order->Status,
                'OrderDetails' => json_decode($Order->OrderDetails,true),
            ];
            array_push($data,$final);
        }
        return response()->json(
            [
                'success'=>1, 
                'message' => 'Order List', 
                'data' => $data
            ],200);
    }

}
