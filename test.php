<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Customer;
use App\Models\Nas;
use App\Models\Transaction;
use App\Services\OtpService;
use Illuminate\Http\Request;
use App\Models\CustomerPackage;
use App\Models\CustomerToken;
use App\Services\RadiusService;
use Illuminate\Validation\Rule;
use App\Services\PaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Services\CheckExpiryService;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    //call the radius service
    protected RadiusService $radiusService;
    protected PaymentService $paymentService;
    private CheckExpiryService $checkExpiryService;
    protected $sms;

    public function __construct(RadiusService $radiusService, PaymentService $paymentService, OtpService $sms, CheckExpiryService $checkExpiryService)
    {
        $this->radiusService = $radiusService;
        $this->paymentService = $paymentService;
        $this->sms = $sms;
        $this->checkExpiryService = $checkExpiryService;
    }


    //Retrieve Hotspot Data
    public function indexHotspot(Request $request)
    {
        $query = Customer::select(['id', 'phone_number', 'fullname', 'username', 'created_at'])
            ->with(['activePackageSubscription', 'activePackageSubscription.package'])
            ->whereNull('parent_id')
            ->where('service', 'hotspot');

        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_null($isActive)) {
                $query->where('is_active', $isActive);
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('phone_number', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('fullname', 'LIKE', "%{$searchTerm}%");
            });
        }

        $customers = $query->orderByDesc('updated_at')->paginate(15);

        $onlineCustomers = DB::table('radacct')
            ->whereIn('username', $customers->pluck('username'))
            ->whereNull('acctstoptime')
            ->pluck('username')
            ->flip();

        $data = $customers->map(function ($customer) use ($onlineCustomers) {
            return [
                'id' => $customer->id,
                'encrypted_id' => Crypt::encryptString($customer->id),
                'phone_number' => $customer->phone_number,
                'fullname' => $customer->fullname,
                'status' => isset($onlineCustomers[$customer->username]) ? 'online' : 'offline',
                'active_package' => $customer->activePackageSubscription ? [
                    'id' => $customer->activePackageSubscription->id,
                    'expires_at' => $customer->activePackageSubscription->expires_at,
                    'package' => $customer->activePackageSubscription->package->package_name ?? 'No package',
                    'price' => $customer->activePackageSubscription->price ?? 0
                ] : 'No active package',
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Hotspot customers retrieved successfully',
            'data' => $data,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }


    public function indexPPPOE(Request $request)
    {
        $query = Customer::query()->select([
            'customers.id',
            'customers.phone_number',
            'customers.service',
            'customers.fullname',
            'customers.username',
            'customers.password',
            'customers.is_active',
            'customers.balance',
            'customers.email',
            'customers.ONU_SN',
            'customers.Location',
            'customers.Zone',
            'customers.corporate',
            'customers.credit_points',
            'customers.net_points',
            'creator.fullname as creator_name',

            DB::raw('(SELECT expires_at FROM customers_packages WHERE customer_id = customers.id ORDER BY expires_at DESC LIMIT 1) as latest_expires_at'),

            DB::raw('(SELECT 1 FROM radacct WHERE username = customers.username AND acctstoptime IS NULL LIMIT 1) as online_session')
        ])
            ->leftJoin('users as creator', 'customers.created_by', '=', 'creator.id')
            ->whereNull('customers.parent_id')
            ->where('customers.service', 'pppoe');

        if ($request->has('is_active')) {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (!is_null($isActive)) {
                $query->where('customers.is_active', $isActive);
            }
        }
        if ($request->has('corporate')) {
            $corporate = $request->corporate;
            if (in_array($corporate, [0, 1, 2])) {
                $query->where('customers.corporate', $corporate);
            }
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('customers.phone_number', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('customers.username', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('customers.fullname', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('customers.email', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('customers.ONU_SN', 'LIKE', "%{$searchTerm}%");
            });
        }

        $customers = $query->orderByDesc('latest_expires_at')->paginate(15);
        $data = $customers->map(function ($customer) {

            $activeSub = CustomerPackage::with('package')
                ->where('customer_id', $customer->id)
                ->orderByDesc('expires_at')
                ->first();

            return [
                'id' => $customer->id,
                'encrypted_id' => Crypt::encryptString($customer->id),
                'phone_number' => $customer->phone_number,
                'service' => $customer->service,
                'fullname' => $customer->fullname,
                'account' => $customer->username,
                'password' => $customer->password,
                'is_active' => $customer->is_active,
                'status' => is_null($customer->online_session) ? 'Inactive' : 'Active', // This now works
                'credit_points' => $customer->credit_points ?? 0,
                'balance' => $customer->balance ?? 0,
                'email' => $customer->email,
                'ONU_SN' => $customer->ONU_SN,
                'Location' => $customer->Location,
                'Zone' => $customer->Zone,
                'corporate' => $customer->corporate,
                'created_by' => $customer->creator_name,
                'net_points' => $customer->net_points ?? 0,
                'active_package' => $activeSub ? [
                    'id' => $activeSub->id,
                    'expires_at' => $activeSub->expires_at,
                    'package' => optional($activeSub->package)->package_name ?? 'No subscription package found',
                    'price' => $activeSub->price ?? 0
                ] : 'No active package',
            ];
        });

        $summaryCounts = DB::table('customers')
            ->where('service', 'pppoe')
            ->select(
                DB::raw('COUNT(CASE WHEN corporate = 0 THEN 1 END) as total_normal'),
                DB::raw('COUNT(CASE WHEN corporate = 1 THEN 1 END) as total_corporate'),
                DB::raw('COUNT(CASE WHEN corporate = 2 THEN 1 END) as total_amazons'),
                DB::raw('COUNT(CASE WHEN corporate = 0 AND is_active = 1 THEN 1 END) as active_normal'),
                DB::raw('COUNT(CASE WHEN corporate = 1 AND is_active = 1 THEN 1 END) as active_corporate'),
                DB::raw('COUNT(CASE WHEN corporate = 2 AND is_active = 1 THEN 1 END) as active_amazons'),
                DB::raw('COUNT(CASE WHEN corporate = 0 AND is_active = 0 THEN 1 END) as inactive_normal'),
                DB::raw('COUNT(CASE WHEN corporate = 1 AND is_active = 0 THEN 1 END) as inactive_corporate'),
                DB::raw('COUNT(CASE WHEN corporate = 2 AND is_active = 0 THEN 1 END) as inactive_amazons')
            )->first();

        $summary = [
            'totals' => [
                'normal' => $summaryCounts->total_normal ?? 0,
                'corporate' => $summaryCounts->total_corporate ?? 0,
                'amazons' => $summaryCounts->total_amazons ?? 0,
            ],
            'active' => [
                'normal' => $summaryCounts->active_normal ?? 0,
                'corporate' => $summaryCounts->active_corporate ?? 0,
                'amazons' => $summaryCounts->active_amazons ?? 0,
            ],
            'inactive' => [
                'normal' => $summaryCounts->inactive_normal ?? 0,
                'corporate' => $summaryCounts->inactive_corporate ?? 0,
                'amazons' => $summaryCounts->inactive_amazons ?? 0,
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'PPPoE customers retrieved successfully',
            'data' => $data,
            'pppoe_stats' => $summary,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
                'last_page' => $customers->lastPage(),
            ],
        ], 200);
    }

    public function getSuspendedCustomers()
    {
        $suspendedUsers = DB::table('radusergroup')
            ->where('groupname', 'LIKE', 'Disabled_%')
            ->pluck('username')
            ->toArray();

        Log::info("Suspended Users: ", ['usernames' => $suspendedUsers]);

        if (empty($suspendedUsers)) {
            return response()->json([
                'status' => 'success',
                'message' => 'No suspended customers found.',
                'data' => [],
            ], 200);
        }

        $customers = Customer::with(['latestPackageSubscription.package'])
            ->whereIn('username', $suspendedUsers)
            ->get();

        $data = $customers->map(function ($customer) {
            $username = $customer->username;

            $isOnline = DB::table('radacct')
                ->where('username', $username)
                ->whereNull('acctstoptime')
                ->exists();

            return [
                'id' => $customer->id,
                'encrypted_id' => Crypt::encryptString($customer->id),
                'fullname' => $customer->fullname,
                'phone_number' => $customer->phone_number,
                'email' => $customer->email,
                'account' => $customer->username,
                'service' => $customer->service,
                'status' => $isOnline ? 'Active' : 'Inactive',
                'package_status' => 'Suspended',
                'ONU_SN' => $customer->ONU_SN,
                'balance' => $customer->balance ?? 0,
                'credit_points' => $customer->credit_points ?? 0,
                'Location' => $customer->Location,
                'Zone' => $customer->Zone,
                'corporate' => $customer->corporate,
                'active_package' => $customer->latestPackageSubscription ? [
                    'id' => $customer->latestPackageSubscription->id,
                    'expires_at' => optional($customer->latestPackageSubscription->expires_at)->format('Y-m-d H:i:s'),
                    'price' => $customer->latestPackageSubscription->price ?? 0,
                    'package' => $customer->latestPackageSubscription->package->package_name ?? 'No subscription package found',
                ] : 'No active package',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Suspended customers retrieved successfully.',
            'count' => $data->count(),
            'data' => $data,
            'pagination' => [
                'current_page' => 1,
                'per_page' => $data->count(),
                'total' => $data->count(),
                'last_page' => 1,
            ],

        ], 200);
    }

    public function getExpiredCustomers()
    {
        $expiredUsers = DB::table('radusergroup')
            ->where('groupname', 'LIKE', 'Expired_%')
            ->pluck('username')
            ->toArray();

        Log::info("Expired Users: ", ['usernames' => $expiredUsers]);

        if (empty($expiredUsers)) {
            return response()->json([
                'status' => 'success',
                'message' => 'No expired customers found.',
                'data' => [],
            ], 200);
        }

        $customers = Customer::with(['latestPackageSubscription.package'])
            ->whereIn('username', $expiredUsers)
            ->get();

        $data = $customers->map(function ($customer) {
            $username = $customer->username;

            $isOnline = DB::table('radacct')
                ->where('username', $username)
                ->whereNull('acctstoptime')
                ->exists();

            return [
                'id' => $customer->id,
                'encrypted_id' => Crypt::encryptString($customer->id),
                'fullname' => $customer->fullname,
                'phone_number' => $customer->phone_number,
                'email' => $customer->email,
                'account' => $customer->username,
                'service' => $customer->service,
                'ONU_SN' => $customer->ONU_SN,
                'status' => $isOnline ? 'Active' : 'Inactive',
                'package_status' => 'Expired',
                'balance' => $customer->balance ?? 0,
                'credit_points' => $customer->credit_points ?? 0,
                'Location' => $customer->Location,
                'Zone' => $customer->Zone,
                'corporate' => $customer->corporate,
                'active_package' => $customer->latestPackageSubscription ? [
                    'id' => $customer->latestPackageSubscription->id,
                    'expires_at' => optional($customer->latestPackageSubscription->expires_at)->format('Y-m-d H:i:s'),
                    'price' => $customer->latestPackageSubscription->price ?? 0,
                    'package' => $customer->latestPackageSubscription->package->package_name ?? 'No subscription package found',
                ] : 'No active package',
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Expired customers retrieved successfully.',
            'count' => $data->count(),
            'data' => $data,
            'pagination' => [
                'current_page' => 1,
                'per_page' => $data->count(),
                'total' => $data->count(),
                'last_page' => 1,
            ],

        ], 200);
    }


    //get hotspot customer all packages
    public function getCustomerPackages(Customer $customer)
    {
        // Get all customer IDs in the same family
        $familyCustomerIds = Customer::where('phone_number', $customer->phone_number)
            ->where('service', $customer->service)
            ->pluck('id')
            ->toArray();

        $packages = CustomerPackage::with('package')
            ->whereIn('customer_id', $familyCustomerIds)
            ->orderByDesc('created_at')
            ->get();

        $data = $packages->map(function ($cp) {
            $status = $cp->is_active;

            // Check for data depletion
            if ($cp->data_limit_mb && $cp->usage_bytes >= ($cp->data_limit_mb * 1048576)) {
                $status = 'depleted';
            }

            return [
                'id' => $cp->id,
                'package_name' => $cp->package->package_name ?? 'N/A',
                'expires_at' => optional($cp->expires_at)->format('Y-m-d H:i:s'),
                'is_active' => $status,
                'usage_bytes' => round(($cp->usage_bytes / 1000000000), 2),
                'voucher_code' => $cp->voucher_code,
            ];
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Customer packages retrieved successfully.',
            'data' => $data,
        ]);
    }

    //Delete customer package and kickoutuser in radius for fresh restart with new package assignation
    public function deleteCustomerPackages(Customer $customer)
    {
        Log::info('Starting deleteCustomerPackages', [
            'customer_id' => $customer->id,
            'username' => $customer->username
        ]);

        try {
            // Step 1: Get relevant usernames
            $usernames = DB::table('radacct as r')
                ->join('customers as c', 'r.username', '=', 'c.username')
                ->leftJoin('customers_packages as cp', function ($join) {
                    $join->on('cp.customer_id', '=', 'c.id')
                        ->where('cp.is_active', 1);
                })
                ->whereNull('r.acctstoptime')   // online
                ->whereNull('cp.id')            // no active package
                ->where('c.service', 'hotspot') // hotspot users only
                ->pluck('r.username');

            if ($usernames->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'message' => 'No online users without active package found.',
                ]);
            }

            $deletedCount = 0;

            foreach ($usernames as $username) {

                DB::beginTransaction();

                try {
                    // Delete user from RADIUS tables
                    DB::table('radcheck')->where('username', $username)->delete();
                    DB::table('radreply')->where('username', $username)->delete();
                    DB::table('radusergroup')->where('username', $username)->delete();

                    // Get active session before deleting radacct row
                    $activeSession = DB::table('radacct')
                        ->where('username', $username)
                        ->whereNull('acctstoptime')
                        ->first();

                    // Delete radacct record
                    DB::table('radacct')->where('username', $username)->delete();

                    DB::commit();

                    $deletedCount++;

                    // ----------- CoA Disconnect -----------
                    if ($activeSession) {
                        $nas = \App\Models\Nas::where('nasname', $activeSession->nasipaddress)->first();

                        if ($nas) {
                            try {
                                $attributes = [
                                    'acctSessionID' => $activeSession->acctsessionid,
                                    'framedIPAddress' => $activeSession->framedipaddress,
                                ];

                                // Optional: load customer model for logging
                                $customer = Customer::where('username', $username)->first();

                                RadiusService::kickOutUsersByRadius($nas, $customer, $attributes);

                                Log::info("CoA disconnect sent", ['username' => $username]);

                            } catch (\Exception $e) {
                                Log::warning("CoA disconnect failed for $username: " . $e->getMessage());
                            }
                        }
                    }

                } catch (\Exception $e) {
                    DB::rollBack();
                    Log::error("Failed deleting RADIUS data for $username: " . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Deleted $deletedCount online hotspot users without active package.",
            ]);

        } catch (\Exception $e) {
            Log::error("deleteOnlineNoActivePackage ERROR: " . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Server error while processing deletions.',
            ], 500);
        }
    }
    /**
     * Store a newly created customer in the database and RADIUS.
     * Handles PPPoE account/password auto-generation and initial package assignment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $rules = [
            'phone_number' => ['required', 'string', 'max:255', 'regex:/^(254\d{9}|0[17]\d{8})$/'],
            'service' => ['required', 'string', 'max:255', Rule::in(['hotspot', 'pppoe'])],
            'fullname' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:customers'],
            'password' => ['required', 'string', 'max:8'],
            'is_active' => ['boolean'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'credit_points' => ['nullable', 'numeric'],
            'balance' => ['nullable', 'numeric'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'ONU_SN' => ['nullable', 'string', 'max:255'],
            'Location' => ['nullable', 'string'],
            'Zone' => ['nullable', 'string', 'max:255'],
            'corporate' => ['nullable', 'integer'],
            'net_points' => ['nullable', 'numeric'],
            'router_id' => ['nullable', 'integer'],
        ];

        $validatedData = $request->validate($rules);

        try {
            DB::beginTransaction();

            if (isset($validatedData['balance'])) {
                $validatedData['balance'] = -abs($validatedData['balance']);
            }

            $account = explode('@', $validatedData['username'])[0];

            $customer = Customer::create([
                'phone_number' => $validatedData['phone_number'],
                'service' => $validatedData['service'],
                'fullname' => $validatedData['fullname'],
                'account' => $account,
                'username' => $validatedData['username'],
                'password' => $validatedData['password'],
                'is_active' => $validatedData['is_active'] ?? 1,
                'package_id' => $validatedData['package_id'],
                'credit_points' => $validatedData['credit_points'] ?? 0,
                'balance' => $validatedData['balance'] ?? 0,
                'email' => $validatedData['email'],
                'ONU_SN' => $validatedData['ONU_SN'],
                'Location' => $validatedData['Location'],
                'Zone' => $validatedData['Zone'],
                'corporate' => $validatedData['corporate'],
                'net_points' => $validatedData['net_points'] ?? 0,
                'router_id' => $validatedData['router_id'] ?? null,
                'created_by' => Auth::user()->id
            ]);

            $expiresAt = null;
            if ($customer->package_id) {
                $package = Package::find($customer->package_id);
                if ($package) {
                    // If service is PPPOE, set expiry to 10 days. Otherwise, calculate based on package.
                    if ($customer->service === 'pppoe') {
                        $expiresAt = Carbon::now()->addDays(10);
                    } else {
                        $expiresAt = $this->radiusService->calculateExpiresAt($package);
                    }

                    CustomerPackage::create([
                        'customer_id' => $customer->id,
                        'package_id' => $package->id,
                        'price' => $package->price ?? 0,
                        'duration_minutes' => $package->validity
                            ? $this->radiusService->convertToSeconds($package->validity, $package->validity_unit) / 60
                            : null,
                        'expires_at' => $expiresAt,
                        'is_active' => true,
                        'start_date' => Carbon::now(),
                        'end_date' => $expiresAt,
                    ]);
                }
            }

            // Radius data object (expiry will now correctly reflect the 10 days for PPPOE)
            $radiusData = (object) [
                'id' => $customer->id,
                'username' => $customer->username,
                'password' => $customer->password,
                'service' => $customer->service,
                'package_id' => $customer->package_id,
                'is_active' => $customer->is_active,
                'expiry' => $expiresAt,
            ];

            if (!$this->radiusService->createUser($radiusData)) {
                throw new \Exception('Failed to create user in RADIUS system.');
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer created successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer creation failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during customer creation.',
                'error' => $e->getMessage(),
            ], 500); // Changed 201 to 500 as this is an error response
        }
    }


    /**
     * Display the specified customer.
     * Eager loads active package subscription.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Customer $customer)
    {
        // Eager load active subscription + package
        $customer->load('latestPackageSubscription.package', 'creator', 'updator');

        $status = 'offline';
        $package_status = 'Not Connected';

        if ($customer->radacct && is_null($customer->radacct->acctstoptime)) {
            $status = 'online';
        }


        // Step 1: Retrieve username bound to this ID
        $username = $customer->username;

        // Step 2: Query radusergroup table using username
        $radGroup = DB::table('radusergroup')
            ->select('groupname')
            ->where('username', $username)
            ->first();


        // Step 3: Determine package status using SQL-like logic in PHP
        if ($radGroup && str_starts_with($radGroup->groupname, 'package_')) {
            //check if radGroup->username exists in radacct and acctstoptime is null
            if (DB::table('radacct')->where('username', $username)->whereNull('acctstoptime')->exists()) {
                $package_status = 'Active';
                $connectionstatus = true;
            } else {
                $package_status = 'Disconnected';
                $connectionstatus = false;
            }

        } elseif ($radGroup && str_starts_with($radGroup->groupname, 'Expired_')) {
            $package_status = 'Expired';
            $connectionstatus = false;
        } elseif ($radGroup && str_starts_with($radGroup->groupname, 'Disabled_')) {
            $package_status = 'Suspended';
            $connectionstatus = false;
        } else {
            $package_status = 'Not Connected';
            $connectionstatus = false;
        }

        // Structure response and replace null with 0
        $customerData = [
            'id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'service' => $customer->service,
            'fullname' => $customer->fullname,
            'account' => $customer->username,
            'password' => $customer->password,
            'is_active' => $connectionstatus,
            'balance' => $customer->balance ?? 0,
            'email' => $customer->email,
            'ONU_SN' => $customer->ONU_SN,
            'Location' => $customer->Location,
            'Zone' => $customer->Zone,
            'status' => $status,
            'corporate' => $customer->corporate,
            'created_by' => $customer->creator->fullname ?? null,
            'updated_by' => $customer->updator->fullname ?? null,
            'created_at' => $customer->created_at->format('Y-m-d H:i:s'),
            'active_package' => $customer->latestPackageSubscription ? [
                'id' => $customer->latestPackageSubscription->id,
                'expires_at' => $customer->latestPackageSubscription->expires_at->format('Y-m-d H:i:s'),
                'usage_bytes' => round(($customer->latestPackageSubscription->usage_bytes * 0.000000000931322), 2) . 'GB',
                'price' => $customer->latestPackageSubscription->price ?? 0,
                'package_status' => $package_status,
                'voucher_code' => $customer->latestPackageSubscription->voucher_code ?? 'N/A',
                'package' => $customer->latestPackageSubscription->package->package_name ?? 'No subscription package found'
            ] : 'No active package',

        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Customer retrieved successfully.',
            'data' => $customerData,
        ], 200);
    }

    //Get Hotspot Customer Active subscrption
    public function HotspotShow(Customer $customer)
    {
        $customer->load('activeSubscriptions.package');
        $customers = Customer::where('phone_number', $customer->phone_number)
            ->where('service', 'hotspot')
            ->select('id', 'phone_number', 'fullname', 'credit_points', 'net_points')
            ->get();

        if ($customers->isEmpty()) {
            return response()->json(['error' => 'User not found. Please verify OTP first.'], 404);
        }

        $customerIds = $customers->pluck('id')->toArray();

        $activePackages = CustomerPackage::with([
            'package' => function ($query) {
                $query->where('type', 'hotspot');
            }
        ])
            ->where('is_active', '!=', 0)
            ->where('expires_at', '>', now())
            ->whereIn('customer_id', $customerIds)
            ->whereHas('package', function ($query) {
                $query->where('type', 'hotspot');
            })
            ->get();


        // 1. Get all MAC addresses (stored in the 'username' column) for this phone number
        $macAddresses = Customer::where('phone_number', $customer->phone_number)->pluck('username');

        // 2. Check the 'radacct' table directly to see if any of those MACs has an active session
        $isAnyDeviceOnline = DB::table('radacct')
            ->whereIn('username', $macAddresses)
            ->whereNull('acctstoptime')
            ->exists();

        // 3. Set the status based on the result
        $status = $isAnyDeviceOnline ? 'online' : 'offline';

        $customerData = [
            'id' => $customer->id,
            'phone_number' => $customer->phone_number,
            'service' => $customer->service,
            'fullname' => $customer->fullname,
            'status' => $status,
            'credit_points' => $customer->credit_points,
            'net_points' => $customer->net_points,
            'active_packages' => $activePackages->map(function ($subscription) {
                return [
                    'id' => $subscription->id,
                    'package_name' => $subscription->package->package_name,
                    'expires_at' => $subscription->expires_at->format('Y-m-d H:i:s'),
                    'price' => $subscription->price,
                    'voucher_code' => $subscription->voucher_code,
                ];
            })
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Customer retrieved successfully.',
            'data' => $customerData,
        ]);
    }




    /**
     * Update the specified customer's details and associated RADIUS entries.
     * Handles package changes, password updates, and status synchronization.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, Customer $customer)
    {
        $rules = [
            'phone_number' => ['required', 'string', 'max:255'],
            'service' => ['required', 'string', 'max:255', Rule::in(['hotspot', 'pppoe'])],
            'fullname' => ['required', 'string', 'max:255'],
            'account' => ['required', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'package_id' => ['nullable', 'exists:packages,id'],
            'credit_points' => ['nullable', 'numeric'],
            'balance' => ['nullable', 'numeric'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'ONU_SN' => ['nullable', 'string', 'max:255'],
            'Location' => ['nullable', 'string'],
            'Zone' => ['nullable', 'string', 'max:255'],
            'corporate' => ['nullable'],
            'net_points' => ['nullable', 'numeric'],
            'router_id' => ['nullable'],

        ];

        $validatedData = $request->validate($rules);

        try {
            DB::beginTransaction();
            // Log the raw validated data first
            Log::info("Customer Update — Raw validated data", [
                'customer_id' => $customer->id,
                'validated_data' => $validatedData,
            ]);

            //Strip username to store account with no @
            if (!empty($validatedData['account'])) {
                $username = $validatedData['account']; // e.g. 4@pppoe
                $accountParts = explode('@', $username);
                $validatedData['account'] = $accountParts[0]; // 4
            }


            Log::info("Customer Update — Processed username/account", [
                'username' => $username,
                'account' => $validatedData['account'],
            ]);

            // Update customer main details
            // Safely set updated_by if user is authenticated
            if (Auth::check()) {
                $validatedData['updated_by'] = Auth::id();
            }

            $customer->update($validatedData);

            $package = Package::find($validatedData['package_id']);
            // Update or insert package relation
            if (!empty($validatedData['package_id'])) {
                $customerPackage = CustomerPackage::where('customer_id', $customer->id)->first();

                if ($customerPackage) {
                    // update existing
                    $customerPackage->update([
                        'package_id' => $validatedData['package_id'],
                        'price' => $package->price,
                    ]);
                } else {

                    // create new if none exists
                    CustomerPackage::create([
                        'customer_id' => $customer->id,
                        'package_id' => $validatedData['package_id'],
                        'price' => $package->price ?? 0,
                        'duration_minutes' => $package->validity
                            ? $this->radiusService->convertToSeconds($package->validity, $package->validity_unit) / 60
                            : null,
                        'expires_at' => now()->addMonth(),
                        'is_active' => true,
                        'start_date' => Carbon::now(),
                        'end_date' => now()->addMonth(),
                    ]);
                }
            }

            // Sync with RADIUS
            if (!$this->radiusService->updateUser($customer)) {
                throw new \Exception('Failed to update user in RADIUS system.');
            }

            DB::commit();

            self::refreshCustomerInRadius($customer);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer updated successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during customer update.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Update pppoe customer details without touching radius
    public function updatePPPoECustomerDetails(Request $request, Customer $customer)
    {
        $rules = [
            'phone_number' => ['required', 'string', 'max:255'],
            'service' => ['required', 'string', 'max:255', Rule::in(['hotspot', 'pppoe'])],
            'fullname' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'ONU_SN' => ['nullable', 'string', 'max:255'],
            'Location' => ['nullable', 'string'],
            'Zone' => ['nullable', 'string', 'max:255'],
            'corporate' => ['nullable'],

        ];

        $validatedData = $request->validate($rules);

        try {
            DB::beginTransaction();
            // Log the raw validated data first
            Log::info("Customer Update — Raw validated data", [
                'customer_id' => $customer->id,
                'validated_data' => $validatedData,
            ]);

            // Update customer main details
            // Safely set updated_by if user is authenticated
            if (Auth::check()) {
                $validatedData['updated_by'] = Auth::id();
            }

            $customer->update($validatedData);


            DB::commit();
            // capture logs of customer update without radius
            Transaction::create([
                'customer_id' => $customer->id,
                'amount' => 0,
                'service' => 'pppoe',
                'gateway' => 'Customer Update',
                'package_id' => null,
                'phone_number' => $customer->phone_number,
                'payment_phone' => $customer->phone_number,
                'organization_amount' => 'Customer details updated by ' . (Auth::user()->fullname ?? ''),
                'status' => 1,
                'date' => now(),
                'created_by' => Auth::id() ?? null,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Customer updated successfully.'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer update failed: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred during customer update.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getLinkedDevices(Customer $customer)
    {
        $subscriptionRelation = $customer->service === 'pppoe'
            ? 'latestPackageSubscription.package'
            : 'activePackageSubscription.package';

        $linkedDevices = Customer::with([$subscriptionRelation, 'radacct'])
            ->where('phone_number', $customer->phone_number)
            ->where('service', $customer->service)
            ->get();

        $linked_devices = $linkedDevices->map(function ($device) use ($customer) {

            $data = [
                'id' => $device->id,
                'fullname' => $device->username,
                'status' => $device->radacct && is_null($device->radacct->acctstoptime),
            ];

            if ($customer->service === 'pppoe') {

                $subscription = $device->latestPackageSubscription;

                $data['package'] = optional($subscription)->package?->package_name ?? 'N/A';
                $data['expires_at'] = optional($subscription)->expires_at?->format('Y-m-d H:i:s');
                $data['pppoe_username'] = $device->username;
                $data['pppoe_password'] = $device->password;

            } else { // Hotspot

                $subscription = $device->activePackageSubscription;
                $data['voucher_code'] = optional($subscription)->voucher_code ?? '-';
            }

            return $data;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Linked devices retrieved successfully.',
            'data' => $linked_devices,
        ], 200);
    }


    //Get customer related transactions
    public function getCustomerTransaction(Customer $customer)
    {
        $customerId = $customer->parent_id ?? $customer->id;

        $transactions = Transaction::with(['package', 'creator'])
            ->where('customer_id', $customerId)
            ->latest()
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No transactions found for this customer.'
            ], 404);
        }

        $customer_transaction = $transactions->map(function ($transaction) {
            return [
                'id' => $transaction->id,
                'mpesa_code' => $transaction->mpesa_code,
                'amount' => $transaction->amount,
                'package' => $transaction->package?->package_name ?? '-',
                'date' => $transaction->created_at?->format('Y-m-d H:i:s'),
                'note' => $transaction->organization_amount,
                'created_by' => $transaction->creator?->fullname ?? '-',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $customer_transaction,
        ]);
    }
    //get mpesa transanctions for pppoe customer
    public function pppoeCustomerTransaction(Customer $customer)
    {
        $transactions = Transaction::with('package')
            ->where('customer_id', $customer->id)
            ->latest()
            ->get();

        if ($transactions->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No transactions found for this customer.'
            ], 404);
        }

        $customer_transaction = $transactions->map(function ($transaction) {
            return [
                'transaction_time' => $transaction->TransTime,
                'mpesa_code' => $transaction->TransID,
                'amount' => $transaction->TransAmount,
                'note' => $transaction->TransactionDesc,
                'status' => $transaction->status === 1
                    ? 'Completed'
                    : ($transaction->status === 2 ? 'Cancelled' : 'Pending'),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $customer_transaction,
        ]);
    }

    // public function pppoeCustomerTransaction(Customer $customer)
    // {
    //     $account = $customer->account ?? $customer->phone_number;

    //     // --- Fetch Mpesa Transactions ---
    //     $mpesa = MpesaTransactions::where('BillRefNumber', $account)
    //         ->select(
    //             'TransID as mpesa_code',
    //             'TransTime as date',
    //             'TransAmount as amount',
    //             'FirstName as created_by',
    //             'status'
    //         );

    //     // --- Fetch System Transactions ---
    //     $system = Transaction::with('creator')
    //         ->where('customer_id', $customer->id)
    //         ->where('mpesa_code', 'CREDIT')
    //         ->select(
    //             'mpesa_code',
    //             'date',
    //             'amount',
    //             'created_by',
    //             DB::raw("NULL as FirstName"),
    //             'status'
    //         );

    //     // --- Combine both ---
    //     $combined = $system->unionAll($mpesa);

    //     // --- Paginate Combined Results ---
    //     $paginated = DB::table(DB::raw("({$combined->toSql()}) as all_transactions"))
    //         ->mergeBindings($combined->getQuery())
    //         ->orderByDesc('date')
    //         ->paginate(15);

    //     // --- Format the Transactions ---
    //     $customer_transaction = collect($paginated->items())->map(function ($tx) {
    //         $creator = $tx->creator?->fullname ?? $tx->FirstName;

    //         return [
    //             'date'        => $tx->date,
    //             'mpesa_code'  => $tx->mpesa_code ?? 'N/A',
    //             'amount'      => $tx->amount,
    //             'created_by'  => $creator,
    //             'status'      => match ((int) $tx->status) {
    //                 1 => 'Completed',
    //                 2 => 'Cancelled',
    //                 default => 'Pending',
    //             },
    //         ];
    //     });

    //     // --- Return Response ---
    //     return response()->json([
    //         'status' => 'success',
    //         'data' => $customer_transaction,
    //         'pagination' => [
    //             'current_page' => $paginated->currentPage(),
    //             'per_page'     => $paginated->perPage(),
    //             'total'        => $paginated->total(),
    //             'last_page'    => $paginated->lastPage(),
    //         ],
    //     ]);
    // }

    /**
     * Soft delete a customer, deactivating subscriptions and disabling in RADIUS.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */

    public function destroy($id)
    {
        Log::info('Customer id to be deleted is ' . $id);
        try {
            DB::beginTransaction();

            $customer = Customer::find($id);
            if (!$customer) {
                return response()->json(['status' => 'error', 'message' => "Customer not found."], 404);
            }

            // If customer has parent_id, delete all siblings with same parent_id
            if ($customer->parent_id) {
                $customersToDelete = Customer::where('parent_id', $customer->parent_id)->get();
            } else {
                // If parent_id is null, check if it has children
                $children = Customer::where('parent_id', $customer->id)->get();
                if ($children->isEmpty()) {
                    // No children, delete only this customer
                    $customersToDelete = collect([$customer]);
                } else {
                    // Has children, delete parent + all children
                    $customersToDelete = collect([$customer])->merge($children);
                }
            }



            foreach ($customersToDelete as $customerToDelete) {
                // Ensure we have a Customer object
                if (!($customerToDelete instanceof Customer)) {
                    Log::warning("Invalid customer object in deletion loop, skipping.");
                    continue;
                }

                if ($customerToDelete->username) {
                    //Remove the username in all radius tables
                    $this->radiusService->deleteUser($customerToDelete->username);
                    Log::info("Customer {$customerToDelete->username} has been removed in all radius tables.");

                    //construct coa to send and kickoutuser in Microtick
                    $activeSession = DB::table('radacct')
                        ->where('username', $customerToDelete->username)
                        ->whereNull('acctstoptime')
                        ->orderBy('acctstarttime', 'desc')
                        ->first();

                    if ($activeSession) {
                        $nasObj = DB::table('nas')->where('nasname', $activeSession->nasipaddress)->first();
                        if ($nasObj) {
                            $attributes = [
                                'acctSessionID' => $activeSession->acctsessionid,
                                'framedIPAddress' => $activeSession->framedipaddress,
                            ];
                            $this->radiusService->kickOutUsersByRadius($nasObj, $customerToDelete, $attributes);
                        }
                    }
                }

                if ($customerToDelete->activePackageSubscription) {
                    $customerToDelete->activePackageSubscription->update(['is_active' => false, 'end_date' => Carbon::now()]);
                }

                $customerToDelete->update(['is_active' => false]);
                $customerToDelete->forceDelete();
                Log::info("Customer {$customerToDelete->id} deleted Sucessfully.");
            }


            DB::commit();

            $deletedCount = $customersToDelete->count();
            return response()->json(['status' => 'success', 'message' => "Customer(s) deleted successfully. Total deleted: {$deletedCount}"], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer deletion failed: ' . $e->getMessage(), ['customer_id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred during customer deletion.', 'error' => $e->getMessage()], 500);
        }
    }



    /**
     * Retrieve a listing of soft-deleted (trashed) customers.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function trashed()
    {
        $trashedCustomers = Customer::onlyTrashed()->latest()->paginate(15);
        return response()->json(['status' => 'success', 'message' => 'Trashed customers retrieved successfully.', 'data' => $trashedCustomers]);
    }




    /**
     * Count clients by service type (hotspot/pppoe).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function countByService()
    {
        $counts = Customer::select('service', DB::raw('count(*) as total'))
            ->groupBy('service')
            ->get();

        return response()->json(['status' => 'success', 'message' => 'Customer counts by service retrieved successfully.', 'data' => $counts]);
    }

    /**
     * Suspend (deactivate) a customer.
     * This marks them inactive locally, deactivates their subscription, and disables them in RADIUS.
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspend($id)
    {
        try {
            DB::beginTransaction();

            //check if customer exits
            $customer = Customer::find($id);

            if (!$customer) {
                return response()->json(['status' => 'error', 'message' => "Customer not found."], 404);
            }



            $customer->update(['is_active' => false]);
            Log::info("Customer {$customer->id} marked as inactive locally.");

            // Deactivate current active subscription if any
            if ($customer->activePackageSubscription) {

                $customer->activePackageSubscription->update(['is_active' => false, 'end_date' => Carbon::now()]);
                Log::info("Customer {$customer->id} active subscription deactivated due to suspension.");
            }

            // Update user's group to 'Disabled_Plan' in RADIUS
            if ($customer->username) {

                $this->radiusService->updateUserGroupByStatus($customer, false, true); // isDisabled = true
                Log::info("Customer {$customer->id} RADIUS group set to Disabled_Plan.");

                //construct Coa to send data to kick out user offline
                $activeSession = DB::table('radacct')
                    ->where('username', $customer->username)
                    ->whereNull('acctstoptime')
                    ->orderBy('acctstarttime', 'desc')
                    ->first();

                if ($activeSession) {
                    $nasObj = DB::table('nas')->where('nasname', $activeSession->nasipaddress)->first();

                    if ($nasObj) {
                        $attributes = [
                            'acctSessionID' => $activeSession->acctsessionid,
                            'framedIPAddress' => $activeSession->framedipaddress,
                        ];
                        $this->radiusService->kickOutUsersByRadius($nasObj, $customer, $attributes);
                    } else {
                        Log::warning("NAS not found for IP: {$activeSession->nasipaddress}");
                    }
                } else {
                    Log::warning("Customer {$customer->id} has no username, skipping RADIUS operations for soft delete.");
                }
            }

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Customer has been suspended successfully.', 'customer' => $customer->fresh()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer suspension transaction failed: ' . $e->getMessage(), ['customer_id' => $id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred during customer suspension.', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Activate a customer.
     * This marks them active locally and reactivates/creates an active subscription.
     * RADIUS group will be updated accordingly.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function activate(Customer $customer)
    {
        try {
            DB::beginTransaction();


            $customer->update(['is_active' => true]);

            Log::info("Customer {$customer->id} marked as active locally.");

            // If customer has a package_id, ensure an active subscription exists
            if ($customer->package_id) {
                $activeSubscription = $customer->activePackageSubscription;
                if (!$activeSubscription || $activeSubscription->package_id !== $customer->package_id) {
                    // Scenario: No active subscription, or active subscription for a *different* package
                    // Deactivate any old active subscription that might be lingering
                    if ($activeSubscription) {
                        $activeSubscription->update(['is_active' => false, 'end_date' => Carbon::now()]);
                        Log::info("Customer {$customer->id} old active subscription deactivated during activation.");
                    }

                    // Create a new active subscription for the current package_id
                    $package = Package::find($customer->package_id);
                    if ($package) {
                        $expiresAt = $this->radiusService->calculateExpiresAt($package);
                        CustomerPackage::create([
                            'customer_id' => $customer->id,
                            'package_id' => $package->id,
                            'price' => $package->price ?? 0,
                            'duration_minutes' => $package->validity ? $this->radiusService->convertToSeconds($package->validity, $package->validity_unit) / 60 : null,
                            'expires_at' => $expiresAt,
                            'is_active' => true,
                            'start_date' => Carbon::now(),
                            'end_date' => $expiresAt,
                        ]);
                        Log::info("Customer {$customer->id} new subscription created during activation. Package: {$package->id}");
                    }
                } else if ($activeSubscription && $activeSubscription->is_active === false) {
                    // Scenario: An existing subscription for this package exists but is inactive, reactivate it
                    $package = Package::find($customer->package_id);
                    $expiresAt = $this->radiusService->calculateExpiresAt($package); // Recalculate expiry for reactivation/extension
                    $activeSubscription->update([
                        'is_active' => true,
                        'start_date' => Carbon::now(),
                        'expires_at' => $expiresAt,
                        'end_date' => $expiresAt,
                    ]);
                    Log::info("Customer {$customer->id} existing subscription reactivated during activation. Package: {$package->id}");
                }
            }

            // Update user's group back to their assigned package plan in RADIUS
            if ($customer->username) {
                $this->radiusService->updateUserGroupByStatus($customer);
                Log::info("Customer {$customer->id} RADIUS group updated during activation.");
                //construct Coa to send data to kick out user offline
                $activeSession = DB::table('radacct')
                    ->where('username', $customer->username)
                    ->whereNull('acctstoptime')
                    ->orderBy('acctstarttime', 'desc')
                    ->first();

                if ($activeSession) {
                    $nasObj = DB::table('nas')->where('nasname', $activeSession->nasipaddress)->first();

                    if ($nasObj) {
                        $attributes = [
                            'acctSessionID' => $activeSession->acctsessionid,
                            'framedIPAddress' => $activeSession->framedipaddress,
                        ];
                        $this->radiusService->kickOutUsersByRadius($nasObj, $customer, $attributes);
                    } else {
                        Log::warning("NAS not found for IP: {$activeSession->nasipaddress}");
                    }
                } else {
                    Log::warning("Customer {$customer->id} has no username, skipping RADIUS operations for soft delete.");
                }
            } else {
                Log::warning("Customer {$customer->id} has no username, skipping RADIUS group update during activation.");
            }

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Customer has been activated successfully.', 'customer' => $customer->fresh()]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer activation transaction failed: ' . $e->getMessage(), ['customer_id' => $customer->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred during customer activation.', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Set or unset the corporate status for a customer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function setCorporateStatus(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'corporate' => ['required', 'boolean'],
        ]);

        $customer->update(['corporate' => $validated['corporate']]);

        $status = $validated['corporate'] ? 'enabled' : 'disabled';
        Log::info("Customer {$customer->id} corporate status {$status}.", ['account' => $customer->account]);

        return response()->json(['status' => 'success', 'message' => "Customer corporate status has been updated successfully."]);
    }




    /**
     * Deposit credit/money into a customer's balance.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function deposit(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            DB::beginTransaction();
            $amount = $validated['amount'];
            $note = $validated['note'] ?? null;

            // Find the root account associated with this customer's phone number.
            $rootCustomer = Customer::where('phone_number', $customer->phone_number)
                ->whereNull('parent_id')
                ->lockForUpdate()
                ->firstOrFail();

            if ($customer->service === 'hotspot') {
                // Always deposit to the root account's credit_points
                $rootCustomer->increment('credit_points', $amount);
                Log::info("Deposit of {$amount} made to ROOT customer {$rootCustomer->id}. New credit_points: {$rootCustomer->credit_points}");
            } else {
                // For PPPoE customers, update balance
                $customer->balance = ($customer->balance ?? 0) + $amount;
                Log::info("Deposit of {$amount} made to customer {$customer->id} balance. New balance: {$customer->balance}");
            }

            $customer->save();
            $customer->refresh();

            //pass the transaction to the transaction table
            Transaction::create([
                'customer_id' => $customer->id,
                'linked_device_id' => $validatedData['linked_device_id'] ?? null,
                'amount' => $amount,
                'service' => $customer->service,
                'gateway' => 'CREDIT_POINTS',
                'mpesa_code' => 'CREDIT',
                'organization_amount' => $note ?? '',
                'created_by' => Auth::user()->id,
                'date' => now(),

            ]);

            DB::commit();
            return response()->json(['status' => 'success', 'message' => 'Deposit successfully completed.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Customer deposit transaction failed: ' . $e->getMessage(), ['customer_id' => $customer->id, 'amount' => $validated['amount'], 'trace' => $e->getTraceAsString()]);
            return response()->json(['status' => 'error', 'message' => 'An error occurred during deposit.', 'error' => $e->getMessage()], 500);
        }
    }


    /**
     * Refresh user's session in Radius (by forcing a disconnect).
     *
     * @param  \App\Models\Customer  $customer
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshRadius(Customer $customer)
    {
        Log::info("Radius refresh (disconnect) initiated for customer {$customer->id}.", ['username' => $customer->username]);

        //Disconnect user to reauthenticate and apply any updated RADIUS attributes
        if ($customer->username) {
            $success = $this->radiusService->disconnectUser($customer->username);
            if ($success) {
                return response()->json(['message' => 'Radius disconnect command sent for ' . $customer->username]);
            } else {
                return response()->json(['message' => 'Failed to send Radius disconnect command.'], 500);
            }
        } else {
            Log::warning("Customer {$customer->id} has no username, skipping RADIUS refresh operation.");
            return response()->json(['message' => 'Customer has no username, no RADIUS refresh performed.'], 200);
        }
    }


    //update expiry
    public function updateCustomerExpiry(Request $request, $customerId)
    {
        $validated = $request->validate([

            'expires_at' => ['required', 'date'],
            'send_sms' => ['required', 'in:true,false,1,0,on'],
        ]);

        //log the incoming date format
        // Log::info("Requested expiry update for customer ID {$customerId} to date: {$validated['expires_at']}");

        try {

            DB::beginTransaction();

            $customer = Customer::with('activePackageSubscription.package')->findOrFail($customerId);
            // Log::info("Updating expiry for customer {$customer->id} to {$validated['expires_at']}");

            $subscription = CustomerPackage::where('customer_id', $customer->id)
                // ->where('is_active', 1)
                ->first();

            Log::alert("Current subscription for customer {$customer->id}: " . ($subscription ? "ID {$subscription->id}, Expires at {$subscription->expires_at}" : "None"));

            if (!$subscription) {
                return response()->json([
                    'message' => 'No active subscription found for this customer.'
                ], 404);
            }

            $newExpiry = Carbon::parse($validated['expires_at']);
            //log the parsed new expiry date
            // Log::info("Parsed new expiry date for customer {$customer->id}: {$newExpiry->toDateString()}");

            if ($newExpiry->isPast()) {
                // Expire subscription
                $subscription->update([
                    'is_active' => 0,
                    'end_date' => $newExpiry,
                ]);
                $customer->update(['package_id' => null]);

                Log::info("Subscription {$subscription->id} expired for customer {$customer->id}.");

                // RADIUS: move to Disabled group and remove expiry
                if ($customer->username) {
                    $this->radiusService->updateUserGroupByStatus($customer, true, false);

                    // Remove RADIUS expiry attribute for expired users
                    DB::table('radcheck')
                        ->where('username', $customer->username)
                        ->where('attribute', 'Expiration')
                        ->delete();
                    Log::info("RADIUS expiry attribute removed for expired customer {$customer->id}");

                    $this->radiusService->disconnectUser($customer->username);
                }
            } else {
                // Extend subscription
                $subscription->update([
                    'is_active' => 1,
                    'expires_at' => $newExpiry,
                    'end_date' => $newExpiry,
                    'updated_by' => Auth::id() ?? null,
                ]);
                $customer->update(['package_id' => $subscription->package_id]);

                Log::info("Subscription {$subscription->id} extended to {$newExpiry} for customer {$customer->id}.");

                // RADIUS: move to package group and update expiry
                if ($customer->username) {
                    //refresh the customer object to pick the new updates
                    $customer->refresh();
                    $this->radiusService->updateUserGroupByStatus($customer, false, false);

                    // Update RADIUS expiry attribute using service method
                    $package = $subscription->package;
                    $expiresAt = $newExpiry;

                    DB::table('radcheck')
                        ->where('username', $customer->username)
                        ->where('attribute', 'Expiration')
                        ->delete();

                    DB::table('radcheck')->insert([
                        'username' => $customer->username,
                        'attribute' => 'Expiration',
                        'op' => ':=',
                        'value' => $expiresAt->format('M d Y H:i')
                    ]);
                    Log::info("RADIUS expiry attribute updated to {$expiresAt->format('M d Y H:i')} for customer {$customer->id}");

                    $this->radiusService->disconnectUser($customer->username);
                }
            }

            DB::commit();

            $contacts = $customer->phone_number;
            $message = "Dear customer, your subscription expiry date has been updated to: {$newExpiry->toDateString()}. Clarification or inquiry HELPLINE : 0702026544. Thank you for choosing Amazons Network.";

            if ($validated['send_sms'] === 'true') {
                Log::info("SMS notification enabled for expiry update of customer {$customer->id}.");
                $this->sms->sendBulk($contacts, $message);
                } else {
                Log::info("Expiry updated but SMS notification disabled for expiry update of customer {$customer->id}.");
            }
            //sent sms to customer about expiry update
            // $this->sms->sendBulk($contacts, $message);

            //Log the epiry details
            Transaction::create([
                'customer_id' => $customer->id,
                'linked_device_id' => $validatedData['linked_device_id'] ?? null,
                'amount' => 0,
                'service' => $customer->service,
                'gateway' => 'CREDIT_POINTS',
                'mpesa_code' => 'EXPIRY_UPDATE',
                'organization_amount' => $note ?? 'Updated Expiry to ' . $newExpiry->toDateString(),
                'created_by' => Auth::user()->id,
                'date' => now(),

            ]);


            return response()->json([
                'status' => 'success',
                'message' => 'Expiry updated successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Expiry update failed: " . $e->getMessage(), [
                'customer_id' => $customerId,
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'message' => 'An error occurred while updating expiry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    //Update Customer Active Subscription Expiry
    public function updateHotspotExpiry(Request $request)
    {
        $validated = $request->validate([
            'package_id' => ['required', 'exists:customers_packages,id'],
            'expires_at' => ['required', 'date'],
        ]);

        try {
            DB::beginTransaction();

            $activePackages = CustomerPackage::with('package')
                ->where('id', $validated['package_id'])
                // ->where('is_active', true)
                ->first();

            //$customer = Customer::findOrFail($customerId);
            $subscription = $activePackages;

            if (!$subscription) {
                return response()->json(['message' => 'No active subscription found.'], 404);
            }

            $newExpiry = Carbon::parse($validated['expires_at']);

            $subscription->update([
                'expires_at' => $newExpiry,
                'is_active' => true,
            ]);

            // get the customer using the phone number from the subscription relation
            $customer = Customer::where('phone_number', $subscription->customer->phone_number)->first();

            //Log the epiry details
            Transaction::create([
                'customer_id' => $customer->id,
                'linked_device_id' => $validatedData['linked_device_id'] ?? null,
                'amount' => 0,
                'service' => 'hotspot',
                'gateway' => '-',
                'mpesa_code' => 'EXPIRY_UPDATE',
                'organization_amount' => $note ?? 'Updated Expiry to ' . $newExpiry->toDateString(),
                'created_by' => Auth::user()->id,
                'date' => now(),

            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Expiry updated successfully',
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'An error occurred while updating expiry.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function movePackage(Request $request)
    {
        // Validate input
        $validated = $request->validate([
            'voucher_code' => 'required|string',
            'phone_number' => 'required|string',
        ]);

        $inputPhone = $request->input('phone_number');
        $phoneNumber = str_starts_with($inputPhone, '0') ? '254' . substr($inputPhone, 1) : $inputPhone;

        try {
            DB::beginTransaction();

            // Step 1: Find the customer package by voucher code
            $customerPackage = CustomerPackage::where('voucher_code', $validated['voucher_code'])
                ->first();

            if (!$customerPackage) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package not found with the provided voucher code',
                ], 404);
            }

            // Store old values for logging
            $oldCustomerId = $customerPackage->customer_id;
            $oldParentId = $customerPackage->parent_id;

            //Get the Phonenumber and fullname of the client from the old customer id
            $oldPhoneNumber = $customerPackage->customer?->phone_number;

            // Step 2: Find the target customer by phone number where parent_id is null
            $targetCustomer = Customer::where('phone_number', $phoneNumber)
                ->whereNull('parent_id')
                ->first();

            if (!$targetCustomer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found with the provided phone number or customer is not a parent account',
                ], 404);
            }

            // Step 3: Check if trying to move to the same customer
            if ($oldCustomerId === $targetCustomer->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Package already belongs to this customer',
                ], 400);
            }

            // Step 4: Update the customer package with the new customer_id and parent_id
            $customerPackage->update([
                'customer_id' => $targetCustomer->id,
                'parent_id' => $targetCustomer->id,
            ]);
            // Step 5: Log the transfer in the Transaction table
            Transaction::create([
                'customer_id' => $targetCustomer->id,
                'service' => 'package_transfer',
                'gateway' => 'package_transfer',
                'external_ref' => $validated['voucher_code'],
                'mpesa_code' => "Transfer->FROM:{$oldPhoneNumber} TO:{$phoneNumber}",
                'date' => now(),
                'package_id' => $customerPackage->package_id,
                'status' => 1,
                'created_by' => Auth::id() ?? null,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Package moved successfully',
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to move package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }



    //Initiate STK Push for a customer
    // public function initiateStkPush(Request $request, Customer $customer)
    // {
    //     $validated = $request->validate([
    //         'phone_number' => ['sometimes', 'nullable', 'regex:/^(\+?254\d{9}|0[17]\d{8})$/'],
    //         'amount' => ['required', 'numeric', 'min:1'],
    //     ]);

    //     // If phone_number is provided in request, update customer's phone_number
    //     if (isset($validated['phone_number'])) {
    //         // Normalize phone number to international format
    //         $normalizedPhone = preg_replace('/^0/', '254', $validated['phone_number']);
    //     }
    //      $accountRef ='PPOE-STK';

    //     try {
    //         DB::beginTransaction();

    //         $result = $this->paymentService->initiatePush2(
    //             $validated['amount'],
    //             $customer->phone_number ?? $normalizedPhone,
    //             $accountRef 
    //         );
    //         Log::info("Data sent to iniatestk " .$result);

    //         if (!$result || !isset($result['CheckoutRequestID'])) {
    //             throw new \Exception('STK Push failed this time.');
    //         }

    //         // Store pending transaction
    //         if (isset($result['CheckoutRequestID'])) {
    //             Transaction::create([
    //                 'customer_id' => $customer->id,
    //                 'amount' => $validated['amount'],
    //                 'service' => 'PPPOE',
    //                 'phone_number' =>  $normalizedPhone,
    //                 'payment_phone' =>  $normalizedPhone,
    //                 'checkout_id' => $result['CheckoutRequestID'],
    //                 'status' => '0',
    //                 'gateway' => 'MPESA',
    //                 'date' => now(),
    //             ]);
          
    //             return response()->json([
    //                 'status' => 'success',
    //                 'message' => 'STK Push initiated for ' . $customer->phone_number ?? $normalizedPhone,
    //                 'confirmation_code' => $result['CheckoutRequestID'],
    //             ], 200);
    //         }

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         Log::error('STK Push failed: ' . $e->getMessage());
    //         return response()->json([
    //             'status' => 'error',
    //             'message' => 'Failed to initiate STK Push'
    //         ], 500);
    //     }
    // }
public function initiateStkPush(Request $request, Customer $customer)
{
    $validated = $request->validate([
        'phone_number' => ['sometimes', 'nullable', 'regex:/^(\+?254\d{9}|0[17]\d{8})$/'],
        'amount' => ['required', 'numeric', 'min:1'],
    ]);

    // Get the phone number to use
    $phone = $validated['phone_number'] ?? $customer->phone_number;
    
    // Normalize phone number: 07XXXXXXXX -> 2547XXXXXXXX, 01XXXXXXXX -> 2541XXXXXXXX
    $normalizedPhone = preg_replace('/^0/', '254', $phone);
    
    Log::info("Original phone: {$phone}, Normalized: {$normalizedPhone}");
    
    $accountRef = 'PPOE-STK';

    try {
        DB::beginTransaction();

        $result = $this->paymentService->initiatePush2(
            $validated['amount'],
            $normalizedPhone,
            $accountRef 
        );
        
        Log::info("++++++++++++++++++++++++++++++++++++++++++++++++++++++Data sent to initiatestk", ['result' => $result]);

        if (!$result || !isset($result['CheckoutRequestID'])) {
            throw new \Exception('STK Push failed this time.');
        }

        // Store pending transaction
        Transaction::create([
            'customer_id' => $customer->id,
            'amount' => $validated['amount'],
            'service' => 'PPPOE',
            'phone_number' => $normalizedPhone,
            'payment_phone' => $normalizedPhone,
            'checkout_id' => $result['CheckoutRequestID'],
            'status' => '0',
            'gateway' => 'MPESA',
            'date' => now(),
        ]);

        DB::commit();
      
        return response()->json([
            'success' => true,
            'message' => 'STK Push initiated for ' . $normalizedPhone,
            'checkout_id' => $result['CheckoutRequestID'],
        ], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('STK Push failed: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'message' => 'Failed to initiate STK Push'
        ], 500);
    }
}


    public function checkStkStatus($checkoutId)
{
    try {

        DB::beginTransaction();

        // Find transaction
        $transaction = Transaction::where('checkout_id', $checkoutId)
            ->where('service', 'PPPOE')
            ->where ('status', '0') // Only check pending transactions
            ->first();

        Log::info("+++++++++++++++++++++++++++++++++++++++++++++++++Checking STK status for checkout ID: {$checkoutId}", ['transaction_id' => $transaction ? $transaction->id : null]);    

        if (!$transaction) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction not found'
            ], 404);
        }
        
                try {
                 $result = $this->paymentService->queryStk2($checkoutId);
        } catch (\Exception $e) {
            Log::error("STK Query Failed: " . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Connection error'], 500);
        }


        if (!$result) {
            throw new \Exception('Failed to query STK status');
        }

        $resultCode = $result['ResultCode'] ?? null;
        Log::info("++++++++++++++++++++++++++++++++++++++++++++++++++++++STK Query Result for checkout ID: {$checkoutId}", ['result' => $result]);

        // SUCCESS
        if ($resultCode == 0) {

            // Prevent double processing
            if ($transaction->status == 1) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Transaction already completed'
                ]);
            }
            Log::info("++++++++++++++++++++++++++++++++++++++++++++++++++++++Processing successful STK transaction for checkout ID: {$checkoutId}", ['transaction_id' => $transaction->id]);

            $customer = Customer::where('phone_number', $transaction->phone_number)->first();
            Log::info("++++++++++++++++++++++++++++++++++++++++++++++++++++++Customer found for STK transaction", ['customer_id' => $customer ? $customer->id : null, 'phone_number' => $transaction->phone_number]);

            if (!$customer) {
                throw new \Exception('Customer not found');
            }

            // Affect customer balance
            $customer->balance += $transaction->amount;
            $customer->save();
            Log::info("++++++++++++++++++++++++++++++++++++++++++++++++++++++Customer balance updated for STK transaction", ['customer_id' => $customer->id, 'new_balance' => $customer->balance]);

            // Update transaction
            $transaction->status = 1;
            $transaction->mpesa_code = $result['MpesaReceiptNumber'] ?? null;
            $transaction->save();

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Payment successful',
                'amount' => $transaction->amount
            ]);
        }

        // FAILED
        if ($resultCode != 0) {

            $transaction->status = 2;
            $transaction->save();

            DB::commit();

            return response()->json([
                'status' => 'failed',
                'message' => $result['ResultDesc'] ?? 'Payment failed'
            ]);
        }

    } catch (\Exception $e) {

        DB::rollBack();

        Log::error('STK Status check failed: ' . $e->getMessage());

        return response()->json([
            'status' => 'error',
            'message' => 'STK status check failed'
        ], 500);
    }
}

    /**
     * Generate the next available PPPoE account name.
     *
     *
     */
    public function pppoeAccounts()
    {
        try {
            // Get the highest numeric account number from all PPPoE customers
            $lastNumber = Customer::withTrashed()
                ->where('service', 'pppoe')
                ->whereRaw('account REGEXP "^[0-9]+$"') // Only numeric accounts
                ->orderByRaw('CAST(account AS UNSIGNED) DESC')
                ->value('account');

            $nextNumber = $lastNumber ? (int) $lastNumber + 1 : 40000;
            $username = $nextNumber . '@pppoe';

            Log::info('Next username is: ' . $username);

            return response()->json([
                'success' => true,
                'message' => 'Next customer username generated successfully',
                'pppoe_account' => $username
            ], 200);

        } catch (\Exception $e) {
            Log::error('PPPoE username generation failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate PPPoE username. Please try again later.'
            ], 500);
        }
    }

    public function getHotspotStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();
        $endOfWeek = Carbon::now()->endOfWeek();

        $stats = [
            'totalUsers' => Customer::where('service', 'hotspot')->count(),
            'activeUsers' => Customer::where('service', 'hotspot')->where('is_active', 1)->count(),
            'onlineNow' => DB::table('radacct')
                ->join('customers', 'radacct.username', '=', 'customers.username')
                ->where('customers.service', 'hotspot')
                ->whereNull('radacct.acctstoptime')
                ->count(),
            'newUsersToday' => Customer::where('service', 'hotspot')->whereDate('created_at', $today)->count(),
            'newUsersThisWeek' => Customer::where('service', 'hotspot')->whereBetween('created_at', [$thisWeek, now()])->count(),
            'newUsersThisMonth' => Customer::where('service', 'hotspot')->whereBetween('created_at', [$thisMonth, now()])->count(),
            'totalPackagesSold' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')->count(),
            'packagesToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->whereDate('customers_packages.created_at', $today)->count(),
            'totalRevenue' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')->sum('customers_packages.price'),
            'revenueToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->whereDate('customers_packages.created_at', $today)->sum('customers_packages.price'),
            'revenueThisMonth' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->whereBetween('customers_packages.created_at', [$thisMonth, now()])->sum('customers_packages.price'),
            'mostBoughtPackage' => DB::table('customers_packages')
                ->join('packages', 'customers_packages.package_id', '=', 'packages.id')
                ->join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->select('packages.package_name', DB::raw('COUNT(*) as count'))
                ->groupBy('packages.package_name')
                ->orderBy('count', 'desc')
                ->value('package_name') ?? 'N/A',
            'topCustomer' => Customer::where('service', 'hotspot')
                ->withCount('customerPackages')
                ->orderBy('customer_packages_count', 'desc')
                ->value('fullname') ?? 'N/A',
            'totalCreditPoints' => Customer::where('service', 'hotspot')->sum('credit_points'),
            'expiredToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->whereDate('customers_packages.expires_at', $today)
                ->where('customers_packages.expires_at', '<', now())->count(),
            'expiringThisWeek' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'hotspot')
                ->where('customers_packages.is_active', 1)
                ->whereBetween('customers_packages.expires_at', [now(), $endOfWeek])->count()
        ];

        return response()->json([
            'success' => true,
            'message' => 'Hotspot stats retrieved successfully',
            'data' => $stats,
        ]);
    }

    public function getPppoeStats()
    {
        $today = Carbon::today();
        $thisWeek = Carbon::now()->startOfWeek();
        $thisMonth = Carbon::now()->startOfMonth();
        $endOfWeek = Carbon::now()->endOfWeek();

        $stats = [
            'totalUsers' => Customer::where('service', 'pppoe')->count(),
            'activeUsers' => Customer::where('service', 'pppoe')->where('is_active', 1)->count(),
            'onlineNow' => DB::table('radacct')
                ->join('customers', 'radacct.username', '=', 'customers.username')
                ->where('customers.service', 'pppoe')
                ->whereNull('radacct.acctstoptime')
                ->count(),
            'newUsersToday' => Customer::where('service', 'pppoe')->whereDate('created_at', $today)->count(),
            'newUsersThisWeek' => Customer::where('service', 'pppoe')->whereBetween('created_at', [$thisWeek, now()])->count(),
            'newUsersThisMonth' => Customer::where('service', 'pppoe')->whereBetween('created_at', [$thisMonth, now()])->count(),
            'totalPackagesSold' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')->count(),
            'packagesToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->whereDate('customers_packages.created_at', $today)->count(),
            'totalRevenue' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')->sum('customers_packages.price'),
            'revenueToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->whereDate('customers_packages.created_at', $today)->sum('customers_packages.price'),
            'revenueThisMonth' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->whereBetween('customers_packages.created_at', [$thisMonth, now()])->sum('customers_packages.price'),
            'mostBoughtPackage' => DB::table('customers_packages')
                ->join('packages', 'customers_packages.package_id', '=', 'packages.id')
                ->join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->select('packages.package_name', DB::raw('COUNT(*) as count'))
                ->groupBy('packages.package_name')
                ->orderBy('count', 'desc')
                ->value('package_name') ?? 'N/A',
            'topCustomer' => Customer::where('service', 'pppoe')
                ->withCount('customerPackages')
                ->orderBy('customer_packages_count', 'desc')
                ->value('fullname') ?? 'N/A',
            'totalBalance' => Customer::where('service', 'pppoe')->sum('balance'),
            'expiredToday' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->whereDate('customers_packages.expires_at', $today)
                ->where('customers_packages.expires_at', '<', now())->count(),
            'expiringThisWeek' => CustomerPackage::join('customers', 'customers_packages.customer_id', '=', 'customers.id')
                ->where('customers.service', 'pppoe')
                ->where('customers_packages.is_active', 1)
                ->whereBetween('customers_packages.expires_at', [now(), $endOfWeek])->count()
        ];

        return response()->json([
            'success' => true,
            'message' => 'PPPoE stats retrieved successfully',
            'data' => $stats,
        ]);
    }

    /**
     * Helper method to create an invoice for a PPPoE customer.
     *
     * @param Customer $customer
     * @param float $price
     * @return void
     */
    protected function invoicePPPoECustomer(Customer $customer, float $price): array
    {
        // Use count or another safe method for invoice numbering
        $invoiceCount = Invoice::count();
        $invoiceNumber = str_pad(($invoiceCount + 1), 6, '0', STR_PAD_LEFT);

        $invoice = Invoice::create([
            'customer_id' => $customer->id,
            'invoice_no' => $invoiceNumber,
            'invoice_description' => json_encode([
                'item_description' => $customer->activePackageSubscription->package->package_name ?? 'PPPoE Service',
                'quantity' => 1,
                'price' => $price,
            ]),
            'due_date' => now()->addDays(30), // Default 30 days due
        ]);
        Log::info("Invoice {$invoiceNumber} created for customer {$customer->id}.");

        // Return invoice data for frontend
        return [
            'status' => 'success',
            'message' => 'Invoice created successfully.',
            'invoice' => $invoice
        ];
    }


    public function showInvoices(Customer $customer)
    {
        $invoices = $customer->invoices()->latest()->paginate(15);
        return response()->json(['status' => 'success', 'message' => 'Invoices retrieved successfully.', 'data' => $invoices]);
    }

    /**
     * Delete an invoice.
     *
     * @param  \App\Models\Invoice  $invoice
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteInvoice(Invoice $invoice)
    {
        try {
            $invoice->delete();
            Log::info("Invoice {$invoice->invoice_no} (ID: {$invoice->id}) deleted.");
            return response()->json(['status' => 'success', 'message' => 'Invoice deleted successfully.'], 201);
        } catch (\Exception $e) {
            Log::error('Invoice deletion failed: ' . $e->getMessage(), ['invoice_id' => $invoice->id, 'trace' => $e->getTraceAsString()]);
            return response()->json(['message' => 'An error occurred during invoice deletion.', 'error' => $e->getMessage()], 500);
        }
    }

    //
    public function getServiceStats()
    {
        $summary = [
            'totals' => [
                'normal' => Customer::where('corporate', 0)->count(),
                'corporate' => Customer::where('corporate', 1)->count(),
                'amazons' => Customer::where('service', 'amazons')->count(),
            ],
            'active' => [
                'normal' => Customer::where('corporate', 0)->where('is_active', 1)->count(),
                'corporate' => Customer::where('corporate', 1)->where('is_active', 1)->count(),
                'amazons' => Customer::where('service', 'amazons')->where('is_active', 1)->count(),
            ],
            'inactive' => [
                'normal' => Customer::where('corporate', 0)->where('is_active', 0)->count(),
                'corporate' => Customer::where('corporate', 1)->where('is_active', 0)->count(),
                'amazons' => Customer::where('service', 'amazons')->where('is_active', 0)->count(),
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Service stats retrieved successfully',
            'summary' => $summary,
        ], 200);
    }

    //After Updates we can refresh the customer attributes like mikrotick-ratelimit
    public function refreshCustomerInRadius($customer)
    {


        $activeSession = DB::table('radacct')
            ->where('username', $customer->username)
            ->whereNull('acctstoptime')
            ->orderBy('acctstarttime', 'desc')
            ->first();

        if (!$activeSession) {
            return ['status' => 'error', 'message' => 'User is not online'];
        }

        $nasObj = DB::table('nas')
            ->where('nasname', $activeSession->nasipaddress)
            ->first();

        if (!$nasObj) {
            return ['status' => 'error', 'message' => 'NAS not found'];
        }

        $attributes = [
            'acctSessionID' => $activeSession->acctsessionid,
            'framedIPAddress' => $activeSession->framedipaddress,
        ];

        $package = Package::findOrFail($customer->package_id);

        if (!$package) {
            return ['status' => 'error', 'message' => 'Package or bandwidth not found'];
        }


        $down = $package->rate_down . $package->rate_down_unit;
        $up = $package->rate_up . $package->rate_up_unit;


        $CoAData = $down . "/" . $up;

        $result = $this->radiusService->sendCoA($nasObj, $customer, $attributes, $CoAData);

        return $result
            ? ['status' => 'success', 'message' => 'CoA sent and ACK received']
            : ['status' => 'error', 'message' => 'CoA failed or NAK received'];
    }

    //Get live data from both services
    public function liveUsage(Customer $customer)
    {
        try {
            // Log::info('Live usage request for customer: ' . $customer->username);

            $isOnline = DB::table('radacct')
                ->where('username', $customer->username)
                ->whereNull('acctstoptime')
                ->orderBy('acctstarttime', 'desc')
                ->first();

            if (!$isOnline) {
                return response()->json([
                    'timestamp' => now()->format('H:i:s'),
                    'download' => 0,
                    'upload' => 0
                ]);
            }

            $nas = Nas::where('nasname', $isOnline->nasipaddress)->first();
            if (!$nas) {
                return response()->json([
                    'timestamp' => now()->format('H:i:s'),
                    'download' => 0,
                    'upload' => 0
                ]);
            }

            $client = new \RouterOS\Client([
                'host' => $nas->nasname,
                'user' => $nas->nasname ?? 'admin',
                'pass' => $nas->secret,
                'port' => $nas->api_port ?? 8728,
                'timeout' => 10,
            ]);

            if (strtolower($customer->service) === 'hotspot') {
                $query = (new \RouterOS\Query('/ip/hotspot/active/print'))
                    ->where('user', $customer->username);

                // First reading
                $firstSession = $client->query($query)->read();
                if (empty($firstSession)) {
                    return response()->json([
                        'timestamp' => now()->format('H:i:s'),
                        'download' => 0,
                        'upload' => 0
                    ]);
                }

                $first = $firstSession[0];
                $firstBytesIn = (int) $first['bytes-in'];
                $firstBytesOut = (int) $first['bytes-out'];

                // Log::info('First reading - In: ' . $firstBytesIn . ', Out: ' . $firstBytesOut);

                sleep(1);

                // Second reading
                $secondSession = $client->query($query)->read();
                if (empty($secondSession)) {
                    return response()->json([
                        'timestamp' => now()->format('H:i:s'),
                        'download' => 0,
                        'upload' => 0
                    ]);
                }

                $second = $secondSession[0];
                $secondBytesIn = (int) $second['bytes-in'];
                $secondBytesOut = (int) $second['bytes-out'];

                // Log::info('Second reading - In: ' . $secondBytesIn . ', Out: ' . $secondBytesOut);

                // Calculate bytes per second, then convert to bits per second
                $uploadBps = ($secondBytesIn - $firstBytesIn) * 8;
                $downloadBps = ($secondBytesOut - $firstBytesOut) * 8;

                // Log::info('Calculated speeds - Download: ' . $downloadBps . ' bps, Upload: ' . $uploadBps . ' bps');

                return response()->json([
                    'timestamp' => now()->format('H:i:s'),
                    'download' => round($downloadBps / 1000000, 2),
                    'upload' => round($uploadBps / 1000000, 2)
                ]);

            } else {
                $query = (new \RouterOS\Query('/ppp/active/print'))
                    ->where('name', $customer->username);

                $activeSession = $client->query($query)->read();

                if (empty($activeSession)) {
                    return response()->json([
                        'timestamp' => now()->format('H:i:s'),
                        'download' => 0,
                        'upload' => 0
                    ]);
                }

                $interfaceName = '<pppoe-' . $customer->username . '>';

                $trafficQuery = new \RouterOS\Query('/interface/monitor-traffic');
                $trafficQuery->equal('interface', $interfaceName);
                $trafficQuery->equal('duration', '1');

                $trafficData = $client->query($trafficQuery)->read();

                if (!empty($trafficData)) {
                    $traffic = $trafficData[0];
                    $uploadBps = isset($traffic['tx-bits-per-second']) ? (int) $traffic['tx-bits-per-second'] : 0;
                    $downloadBps = isset($traffic['rx-bits-per-second']) ? (int) $traffic['rx-bits-per-second'] : 0;

                    return response()->json([
                        'timestamp' => now()->format('H:i:s'),
                        'download' => round($downloadBps / 1000000, 2),
                        'upload' => round($uploadBps / 1000000, 2)
                    ]);
                }
            }

            return response()->json([
                'timestamp' => now()->format('H:i:s'),
                'download' => 0,
                'upload' => 0
            ]);

        } catch (\Exception $e) {
            Log::error('MikroTik API error: ' . $e->getMessage());
            return response()->json([
                'timestamp' => now()->format('H:i:s'),
                'download' => 0,
                'upload' => 0
            ]);
        }
    }

    public function vlanTrafficSmart(Nas $nas, string $interface, )
    {
        try {
            $client = new \RouterOS\Client([
                'host' => $nas->nasname,
                'user' => $nas->nasname ?? 'admin',
                'pass' => $nas->secret,
                'port' => $nas->api_port ?? 8728,
                'timeout' => 10,
            ]);

            /**
             * 1️⃣ TOTAL BYTES (since router boot)
             */
            $totalQuery = new \RouterOS\Query('/interface/print');
            $totalQuery->where('name', $interface);

            $totalResult = $client->query($totalQuery)->read();

            $rxTotal = 0;
            $txTotal = 0;

            if (!empty($totalResult)) {
                $iface = $totalResult[0];
                $rxTotal = (int) ($iface['rx-byte'] ?? 0);
                $txTotal = (int) ($iface['tx-byte'] ?? 0);
            }

            /**
             * 2️⃣ LIVE SPEED (1 second sampling)
             */
            $liveQuery = new \RouterOS\Query('/interface/monitor-traffic');
            $liveQuery->equal('interface', $interface);
            $liveQuery->equal('duration', '3');

            $liveResult = $client->query($liveQuery)->read();

            $rxBps = 0;
            $txBps = 0;

            if (!empty($liveResult)) {
                $traffic = $liveResult[0];
                $rxBps = (int) ($traffic['rx-bits-per-second'] ?? 0);
                $txBps = (int) ($traffic['tx-bits-per-second'] ?? 0);
            }

            return response()->json([
                'timestamp' => now()->format('H:i:s'),

                // LIVE SPEED
                'download' => round($rxBps / 1_000_000, 2), // Mbps
                'upload' => round($txBps / 1_000_000, 2), // Mbps

                // TOTAL USAGE
                'rx_bytes' => $rxTotal,
                'tx_bytes' => $txTotal,
                'rx_gb' => round($rxTotal / 1024 / 1024 / 1024, 2),
                'tx_gb' => round($txTotal / 1024 / 1024 / 1024, 2),
            ]);

        } catch (\Exception $e) {
            Log::error('VLAN smart traffic error', [
                'interface' => $interface,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'timestamp' => now()->format('H:i:s'),
                'download' => 0,
                'upload' => 0,
                'rx_bytes' => 0,
                'tx_bytes' => 0,
            ]);
        }
    }
    public function getTopDataUsersByService()
    {
        try {
            $getTopByType = function ($serviceType) {
                // Determine which name column to use based on service type
                $nameColumn = ($serviceType === 'HotSpot') ? 'customers.fullname' : 'customers.last_name';

                return DB::table('radacct')
                    ->leftJoin('customers', 'radacct.username', '=', 'customers.username')
                    ->where('radacct.acctstarttime', '>=', today())
                    ->where('radacct.nasportid', $serviceType)
                    ->select('radacct.username')
                    // Use fullname for Hotspot, last_name for PPPoE (defaults to username if null)
                    ->selectRaw("COALESCE($nameColumn, radacct.username) as name")
                    // Add phone number column
                    ->selectRaw("COALESCE(customers.phone_number, 'N/A') as phone")
                    ->selectRaw('SUM(acctinputoctets) as download')
                    ->selectRaw('SUM(acctoutputoctets) as upload')
                    ->selectRaw('SUM(acctinputoctets + acctoutputoctets) as total_bytes')
                    ->groupBy('radacct.username', 'name', 'phone') // Include phone in groupBy
                    ->orderByDesc('total_bytes')
                    ->limit(5)
                    ->get();
            };

            // Fetch data
            $pppoeUsers = $getTopByType('PPP');
            $hotspotUsers = $getTopByType('HotSpot');

            return response()->json([
                'timestamp' => now()->format('H:i:s'),
                'data' => [
                    'pppoe' => $this->formatUserCollection($pppoeUsers),
                    'hotspot' => $this->formatUserCollection($hotspotUsers),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Top users error', ['msg' => $e->getMessage()]);
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    /**
     * Helper to format the collection and calculate relative percentages
     */
    private function formatUserCollection($users)
    {
        $max = $users->max('total_bytes') ?: 1;

        return $users->map(function ($user) use ($max) {
            return [
                'username' => $user->username,
                'name' => $user->name,
                'phone' => $user->phone,
                'rx_gb' => round($user->download / 1024 / 1024 / 1024, 2),
                'tx_gb' => round($user->upload / 1024 / 1024 / 1024, 2),
                'total_gb' => round($user->total_bytes / 1024 / 1024 / 1024, 2),
                'percentage' => round(($user->total_bytes / $max) * 100, 1),
            ];
        });
    }


    public function clearStaleSession($customerId)
    {
        try {
            Log::info("Starting clearStaleSession for customer ID: {$customerId}");

            $customer = Customer::findOrFail($customerId);
            Log::info("Found customer: {$customer->username}");

            if (is_null($customer->parent_id)) {
                Log::info("Customer is parent - clearing sessions for parent and children");
                $customers = Customer::where('phone_number', $customer->phone_number)->get();
            } else {
                Log::info("Customer is child - finding parent and clearing sessions for family");
                $parent = Customer::findOrFail($customer->parent_id);
                $customers = Customer::where('phone_number', $parent->phone_number)->get();
            }

            $totalDeleted = 0;
            foreach ($customers as $cust) {
                $deleted = DB::table('radacct')
                    ->where('username', $cust->username)
                    ->whereNull('acctstoptime')
                    ->delete();

                $totalDeleted += $deleted;
                Log::info("Cleared {$deleted} stale sessions for {$cust->username}");
            }

            if ($totalDeleted === 0) {
                return response()->json([
                    'success' => true,
                    'message' => 'No stale sessions found for the customer.'
                ], 404);
            }

            Log::info("Total cleared: {$totalDeleted} stale sessions");

            return response()->json([
                'success' => true,
                'message' => "Cleared {$totalDeleted} stale sessions for customer family."
            ]);

        } catch (\Exception $e) {
            Log::error("clearStaleSession failed: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while clearing stale sessions.'
            ], 500);
        }
    }





    //check online with no active package
    //'encrypted_id'   => Crypt::encryptString($customer->id),
    public function onlineNoActivePackage()
    {
        $data = DB::table('radacct as r')
            ->join('customers as c', 'r.username', '=', 'c.username')
            ->leftJoin('customers_packages as cp', function ($join) {
                $join->on('cp.customer_id', '=', 'c.id')
                    ->where('cp.is_active', 1);
            })
            ->whereNull('r.acctstoptime')   // Only online users
            ->whereNull('cp.id')            // No active package
            ->where('c.service', 'hotspot') // Hotspot customers only
            ->select('c.fullname', 'c.username', 'c.phone_number')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Online customers with no active package retrieved successfully',
            'count' => $data->count(),
            'data' => $data
        ]);
    }

    // delete the no packages users
    public function deleteOnlineNoActivePackage()
    {
        // Get the usernames that match the delete conditions
        $usernames = DB::table('radacct as r')
            ->join('customers as c', 'r.username', '=', 'c.username')
            ->leftJoin('customers_packages as cp', function ($join) {
                $join->on('cp.customer_id', '=', 'c.id')
                    ->where('cp.is_active', 1);
            })
            ->whereNull('r.acctstoptime')   // online
            ->whereNull('cp.id')            // no active package
            ->where('c.service', 'hotspot') // hotspot users
            ->pluck('r.username');

        if ($usernames->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No online users without active package found to delete.',
            ]);
        }

        foreach ($usernames as $username) {
            // Delete those users from radacct
            DB::table('radacct')
                ->where('username', $username)
                ->whereNull('acctstoptime')
                ->delete();

            DB::table('radcheck')
                ->where('username', $username)
                ->delete();

            DB::table('radreply')
                ->where('username', $username)
                ->delete();

            DB::table('radusergroup')

                ->where('username', $username)
                ->delete();

            $customer = Customer::where('username', $username)->first();

            $activeSession = DB::table('radacct')
                ->where('username', $username)
                ->whereNull('acctstoptime')
                ->orderBy('acctstarttime', 'desc')
                ->first();

            if ($activeSession) {

                $nasObj = DB::table('nas')->where('nasname', $activeSession->nasipaddress)->first();

                if ($nasObj) {
                    $attributes = [
                        'acctSessionID' => $activeSession->acctsessionid,
                        'framedIPAddress' => $activeSession->framedipaddress,
                    ];
                    $this->radiusService->kickOutUsersByRadius($nasObj, $customer, $attributes);
                } else {
                    Log::warning("NAS not found for IP: {$activeSession->nasipaddress}");
                }
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Deleted online users with no active package from radacct successfully.',
            'count' => $usernames->count()
        ]);
    }

    public function PppoeClient()
    {
        $customers = Customer::where('is_active', true)
            ->where('service', 'pppoe')
            ->select('id', 'fullname', 'email')
            ->get();

        return response()->json([
            'results' => $customers->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'text' => $customer->fullname . ' - ' . optional($customer->package)->package_name,
                ];
            }),
        ]);
    }

    // 2. Generate and Store the Token
    public function generateToken(Request $request)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'name' => 'required|string'
        ]);

        // Generate a secure random string
        $token = Str::random(60);

        // Save to the new table
        $customerToken = CustomerToken::create([
            'customer_id' => $request->customer_id,
            'token' => $token,
            'name' => $request->name,
            'is_active' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Token generated successfully,Toggle to make it active.',
        ]);
    }
    public function getUsageByToken(Request $request)
    {
        // 1. Get the token from the header
        $token = $request->header('X-Customer-Token');

        if (!$token) {
            return response()->json(['error' => 'Token not provided'], 401);
        }

        // 2. Find the token owner
        $tokenRecord = CustomerToken::with('customer')->where('token', $token)
            ->where('is_active', 1)
            ->first();

        if (!$tokenRecord || !$tokenRecord->customer) {
            return response()->json(['error' => 'Invalid or inactive Token'], 403);
        }

        $customer = $tokenRecord->customer;

        // 3. Call existing liveUsage function
        return $this->liveUsage($customer);
    }

    public function fetchCustomerTokens()
    {
        $tokens = CustomerToken::with('customer:id,fullname')
            ->select('id', 'name', 'token', 'is_active', 'created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tokens
        ]);
    }


    public function toggleTokenStatus($tokenId)
    {
        $token = CustomerToken::findOrFail($tokenId);

        $token->is_active = !$token->is_active;
        $token->save();

        return response()->json([
            'success' => true,
            'message' => $token->is_active
                ? 'Token activated successfully.'
                : 'Token deactivated successfully.',
            'status' => $token->is_active,
        ]);
    }

    public function deleteToken($tokenId)
    {
        $token = CustomerToken::findOrFail($tokenId);

        $token->delete();

        return response()->json([
            'success' => true,
            'message' => 'Token deleted successfully.',
        ]);
    }

     // public function calculatePayment(Request $request)
    // {
    //     $user = auth()->user();
    //     $latestPackage = $user->latestPackage; // Current subscription record
    //     $newPackage = Package::findOrFail($request->package_id);

    //     $now = now();
    //     $remainingDays = ($latestPackage && $latestPackage->expires_at > $now)
    //         ? $now->diffInDays($latestPackage->expires_at)
    //         : 0;

    //     // 1. Logic for Expiry Extension (Staying on the same package)
    //     if ($request->type === 'extension') {
    //         return $this->calculateExtension($newPackage->price, $request->days_to_extend);
    //     }

    //     // 2. Logic for Upgrading
    //     return $this->calculateUpgrade($latestPackage, $newPackage, $remainingDays);
    // }

    // private function calculateUpgrade($latestPackage, $newPackage, $remainingDays)
    // {
    //     $newPrice = $newPackage->price;

    //     // If expired or no previous package, charge full price
    //     if ($remainingDays <= 0 || !$latestPackage) {
    //         return round($newPrice, 0);
    //     }

    //     $oldDailyRate = $latestPackage->package_price / 30;
    //     $newDailyRate = $newPrice / 30;

    //     // Calculate the difference for the remaining period
    //     $upgradeCost = ($newDailyRate - $oldDailyRate) * $remainingDays;

    //     // Ensure we don't return a negative value (in case of a downgrade request)
    //     return max(0, round($upgradeCost, 0));
    // }

    // private function calculateExtension($monthlyPrice, $days)
    // {
    //     $dailyRate = $monthlyPrice / 30;
    //     return round($dailyRate * $days, 0);
    // }

    public function getUpgradeOptions(Request $request, Customer $customer)
    {
        // 1. Inputs from Request
        $customerId = $customer->id;
        $extensionDays = (int) $request->input('days', 30); // Default to 30 if not provided

        $customer = Customer::with('currentPackage')->findOrFail($customerId);
        $currentSub = $customer->latestSubscription; // The active record

        $now = now();
        $remainingDays = ($currentSub && $currentSub->expires_at > $now)
            ? $now->diffInDays($currentSub->expires_at)
            : 0;

        // 2. Fetch all packages available for upgrade (usually higher price than current)
        $currentPrice = $currentSub->package_price ?? 0;
        $availablePackages = Package::where('price', '>=', 1500)->get();

        $calculatedUpgrades = $availablePackages->map(function ($package) use ($currentPrice, $remainingDays) {
            $newPrice = $package->price;

            // --- Logic 1: Extension Calculation ---
            // Based on 30-day month, minimum 1500/= floor
            $dailyRate = $newPrice / 30;
            $extensionCost = max(1500, round($dailyRate * 30, 0));

            // --- Logic 2: Upgrade Pro-rating ---
            if ($remainingDays <= 0) {
                $upgradeCost = $newPrice; // Expired? Pay full price
            } else {
                $oldDailyRate = $currentPrice / 30;
                $newDailyRate = $newPrice / 30;
                // Pay only the difference for the time left
                $upgradeCost = ($newDailyRate - $oldDailyRate) * $remainingDays;
            }

            return [
                'package_id' => $package->id,
                'package_name' => $package->name,
                'monthly_price' => $package->price,
                'upgrade_amount' => max(0, round($upgradeCost, 0)),
                'extension_amount' => $extensionCost,
            ];

        });

        // 3. Return JSON Response
        return response()->json([
            'status' => 'success',
            'customer_info' => [
                'id' => $customer->id,
                'current_package' => $customer->currentPackage->name ?? 'None',
                'days_remaining' => $remainingDays,
                'expiry_date' => $currentSub->expires_at ?? null,
            ],
            'input_context' => [
                'requested_extension_days' => $extensionDays,
            ],
            'packages' => $calculatedUpgrades
        ]);
    }

}
