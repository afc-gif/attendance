<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $query = EmployeeDocument::with('employee');
        if ($request->filled('employee')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $q->where('employee_id', 'like', '%'.$request->employee.'%')
                    ->orWhere('first_name', 'like', '%'.$request->employee.'%')
                    ->orWhere('last_name', 'like', '%'.$request->employee.'%');
            });
        }
        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        $documents = $query->latest()->paginate(20)->appends($request->query());
        $employees = Employee::orderBy('first_name')->get();

        return view('admin.hr.documents.index', compact('documents', 'employees'));
    }

    public function store(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:100'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $file = $validated['document'];
        $storedName = $file->store('hr-documents/'.$employee->employee_id, 'local');
        if (! $storedName) {
            throw new \RuntimeException('Unable to store employee document.');
        }

        EmployeeDocument::create([
            'employee_id' => $employee->id,
            'document_type' => $validated['document_type'],
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => $storedName,
            'disk' => 'local',
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size' => $file->getSize(),
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('success', 'Document uploaded securely.');
    }

    public function download(EmployeeDocument $document): StreamedResponse
    {
        $disk = Storage::disk($document->disk);
        if (! $disk->exists($document->stored_name)) {
            abort(404, 'Document not found.');
        }

        $contents = $disk->get($document->stored_name);

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $document->original_name, [
            'Content-Type' => $document->mime_type,
        ]);
    }

    public function destroy(EmployeeDocument $document)
    {
        $document->delete();

        return back()->with('success', 'Document removed.');
    }
}
