<?php

namespace App\Http\Controllers;

// use Dotenv\Validator;

use App\Exports\EmployeesExport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\Employee;
use App\Models\Position;
// use Barryvdh\DomPDF\PDF;
use PDF;
use RealRashid\SweetAlert\Facades\Alert;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $pageTitle = 'Employee List';

        confirmDelete();

        return view('employee.index', [
            'pageTitle' => $pageTitle
        ]);
    }


    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $pageTitle = 'Create Employee';

        //ELOQUENT
        $positions = Position::all();

        return view('employee.create', compact('pageTitle', 'positions'));

    }



    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $messages = [
            'required' => ':Attribute harus diisi.',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka'
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();
        }

        // Get File
        $file = $request->file('cv');

        if ($file != null) {
            $originalFilename = $file->getClientOriginalName();
            $encryptedFilename = $file->hashName();

            // Store File
            $file->store('public/files');
        }

        // ELOQUENT
        $employee = New Employee;
        $employee->firstname = $request->firstName;
        $employee->lastname = $request->lastName;
        $employee->email = $request->email;
        $employee->age = $request->age;
        $employee->position_id = $request->position;

        if ($file != null) {
            $employee->original_filename = $originalFilename;
            $employee->encrypted_filename = $encryptedFilename;
        }

        $employee->save();

        Alert::success('Added Successfully', 'Employee Data Added Successfully.');

        return redirect()->route('employees.index');
    }




    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $pageTitle = 'Employee Detail';

        //ELOQUENT
        $employee = Employee::find($id);

        return view('employee.show', compact('pageTitle', 'employee'));
    }


    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $pageTitle = 'Employee Edit';

        //ELOQUENT
        $positions = Position::all();
        $employee = Employee::find($id);
        $positions = DB::table('positions')->get();

        return view('employee.edit', compact('pageTitle', 'positions', 'employee'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $messages = [
            'required' => ':Attribute harus diisi.',
            'email' => 'Isi :attribute dengan format yang benar',
            'numeric' => 'Isi :attribute dengan angka'
        ];

        $validator = Validator::make($request->all(), [
            'firstName' => 'required',
            'lastName' => 'required',
            'email' => 'required|email',
            'age' => 'required|numeric',
        ], $messages);

        if ($validator->fails()) {
            return redirect()->back()->withErrors($validator)->withInput();

        }

        //ELOQUENT
        $employee = Employee::find($id);
        $employee->firstname = $request->firstName;
        $employee->lastname = $request->lastName;
        $employee->email = $request->email;
        $employee->age = $request->age;
        $employee->position_id = $request->position;

        $originalFilename = $employee->original_filename;
        $encryptedFilename = $employee->encrypted_filename;

        $file = $request->file('cv');

        if ($file != null) {
            $originalFilename = $file->getClientOriginalName();
            $encryptedFilename = $file->hashName();

            // Store File
            $file->store('public/files');
        }

        if ($employee->original_filename != null) {
            // Ambil nama file lama
            $oldFilename = $employee->encrypted_filename;

            // Hapus file lama dari penyimpanan
            Storage::delete('public/files/'.$oldFilename);
        }

        $employee->original_filename = $originalFilename;
        $employee->encrypted_filename = $encryptedFilename;

        $employee->save();

        Alert::success('Changed Successfully', 'Employee Data Changed Successfully.');

        return redirect()->route('employees.index');
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //ELOQUENT
        $employee = Employee::find($id);

        $oldFilename = $employee->encrypted_filename;
        Storage::delete('public/files/'.$oldFilename);

        $employee->delete();

        Alert::success('Deleted Successfully', 'Employee Data Deleted Successfully.');

        return redirect()->route('employees.index');
    }

    public function downloadFile($employeeId)
    {
        $employee = Employee::find($employeeId);
        $encryptedFilename = 'public/files/'.$employee->encrypted_filename;
        $downloadFilename = Str::lower($employee->firstname.'_'.$employee->lastname.'_cv.pdf');

        if(Storage::exists($encryptedFilename)) {
            return Storage::download($encryptedFilename, $downloadFilename);
        }
    }

    public function getData(Request $request)
    {
        $employees = Employee::with('position');

        if ($request->ajax()) {
            return datatables()->of($employees)
                ->addIndexColumn()
                ->addColumn('actions', function($employee) {
                    return view('employee.actions', compact('employee'));
                })
                ->toJson();
        }
    }

    public function exportExcel()
    {
        return Excel::download(new EmployeesExport, 'employees.xlsx');
    }

    public function exportPdf()
    {
        $employees = Employee::all();

        $pdf = PDF::loadView('employee.export_pdf', compact('employees'));

        return $pdf->download('employees.pdf');
    }

}
