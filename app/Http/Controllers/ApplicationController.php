<?php

namespace App\Http\Controllers;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Pagination\Paginator;
use Spatie\Activitylog\Models\Activity;
use App\Notifications\ApplicationReview;
use Carbon\Carbon;


class ApplicationController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Application::class);
    }

    public function index()
    {
        $applications = Application::with('user')->orderBy('id', 'DESC')->paginate(10, ['*'], 'applications');
        $pendingApplications = $applications->where('status', 'Pending');
        $activities = Activity::with('causer')->orderBy('id', 'DESC')->paginate(10, ['*'], 'logs');

        $moderators = User::with('applications')->role('moderator')->get();
        $administrators = User::with('applications')->role('administrator')->get();

        return view('application.index', [
            'applications' => $applications,
            'pendingApplications' => $pendingApplications,
            'activities' => $activities,
            'moderators' => $activities,
            'administrators' => $administrators,
        ]);
    }

    public function show(Application $application)
    {
        return view('application.show', [
            'application' => $application,
        ]);
    }

    public function create()
    {
        return view('application.create');
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if(Auth::user()->applications->count() >= 5)
            return redirect()->route('home')->with('danger', 'Već imate 5 aplikacija!');
            
        $input = $request->validate([
            'app_text' => 'required|min:200|max:500',
            'char_name' => 'required|max:24|unique:applications|regex:/^[A-Za-z]+_[A-Za-z]+$/',
            'char_password' => 'required|min:6|confirmed',
            'char_dob' => 'required',
            'char_sex' => 'required',
        ]);

        $input['user_id'] = Auth::user()->id;

        DB::beginTransaction();
        $application = Application::create($input);
        DB::commit();
        activity()->log('je kreirao novu aplikaciju za ime ' . $application->char_name);
        return redirect()->route('home')->with('success', 'Poslali ste aplikaciju za kreiranje server računa.');
    }

    public function update(Request $request, Application $application)
    {
        $input = $request->validate([
            'response_text' => 'required',
            'status' => 'required',
        ]);
        $id = null;
        if($input['status'] == "Accepted")
        {
            $id = DB::table('accounts')->insertGetId([
                'registered' => 1,
                'register_date' => Carbon::parse(now()),
                'name' => $application->char_name,
                'password' => "",
                'teampin' => 1,
                'email' => $application->user->email,
                'secawnser' => "",
                'expdate' => "",
                'levels' => 1,
                'age' => $application->char_dob,
                'sex' => $application->char_sex,
                'handMoney' => 1500,
                'bankMoney' => 500,
                'jobkey' => 0,
                'playaskin' => 29,
                'casinocool' => 5,
                'ucp_user_id' => $application->user->id,
            ]);

            $input['account_id'] =  $id;
            $pass = strrev($application->char_password);
			$hashedPass = "COA".$pass.$id;
			$hashedPass = strtoupper(hash('whirlpool', $hashedPass));

            $affected = DB::table('accounts')
              ->where('sqlid', $id)
              ->update(['password' => $hashedPass]);
        }

        $application->update($input);

        $application->user->notify(new ApplicationReview($application));
        activity()->log('odgovor na aplikaciju ID: ' . $application->id . ' / Nick: ' . $application->char_name . ' / Status: ' . $application->status);
        return redirect()->route('application.show', $application)->with('success', 'Application successfully responded.');
    }

    public function destroy(Application $application)
    {
        if($application->status != 'Rejected')
            return redirect()->route('home')->with('danger', 'Application has to be rejected to be deleted.');
        try {
            $application->delete($application);
            return redirect()->route('home')->with('success', 'Application is successfully deleted.');
        } catch (Exception $e) {
            return back()->with('danger', $e);
        }
    }
}
