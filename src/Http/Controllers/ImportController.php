<?php

namespace Vcian\LaravelDataBringin\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Vcian\LaravelDataBringin\Http\Requests\ImportRequest;
use Vcian\LaravelDataBringin\Http\Requests\StoreImportRequest;
use Vcian\LaravelDataBringin\Services\ImportService;

class ImportController extends Controller
{
    /**
     * @param ImportService $importService
     */
    public function __construct(
        private readonly ImportService $importService
    ) {}

    /**
     * @param ImportRequest $request
     * @return View|RedirectResponse
     */
    public function index(ImportRequest $request): View|RedirectResponse
    {
        if($request->step > session('import.step')) {
            return to_route('data_bringin.index');
        }
        $data = [];
        $table = $request->table ?? session('import.table');
        $data['tables'] = $this->importService->getTables();
        $data['tableColumns'] = $table ? $this->importService->getTableColumns($table) : collect();
        $data['selectedTable'] = $table;
        $data['selectedColumns'] = collect(session('import.columns'));
        $data['fileColumns'] = collect(session('import.fileColumns'));
        $data['fileData'] = collect(session('import.data'));
        $data['result'] = collect(session('import.result'));
        return view('data-bringin::import', $data);
    }

    /**
     * @param StoreImportRequest $request
     * @return RedirectResponse
     */
    public function store(StoreImportRequest $request): RedirectResponse
    {
        switch($request->step) {
            case 1:
                session()->forget('import');
                $path = $request->file('file')->getRealPath();
                session(['import.data' => $this->importService->csvToArray($path), 'import.step' => 2]);
                break;
            case 2:
                session([
                    'import.table' => $request->table,
                    'import.columns' => $request->columns,
                    'import.step' => 3
                ]);
                break;
            case 3:
                $fileData = collect(session('import.data'));
                $table = session('import.table');
                $columns = collect(session('import.columns'));
                $insertData = [];
                try {
                    foreach ($fileData as $data) {
                        $prepareData = [];
                        foreach ($columns as $key => $column) {
                            $prepareData[$key] = $data[$column];
                        }
                        $insertData[] = $prepareData;
                    }
                    DB::table($table)->insert($insertData);
                } catch (QueryException $ex) {
                    $errorMsg = 'There is an issue on store data in database.';
                } catch (\Exception $ex) {
                    $errorMsg = $ex->getMessage();
                }
                $result = [
                    'count' => count($insertData),
                    'error' => $errorMsg ?? null
                ];
                session()->forget('import');
                session([
                    'import.result' => $result,
                    'import.step' => 4
                ]);
                break;
        }
        return redirect()->route('data_bringin.index', ['step' => ++$request->step]);
    }


    /**
     * @param int $id
     * @return RedirectResponse
     */
    public function deleteRecord(int $id): RedirectResponse
    {
        try {
            $data = collect(session('import.data'))->reject(function (array $data) use($id) {
                return $data['Id'] == $id;
            })->values();
            session(['import.data' => $data]);
            return redirect()->back()->withSuccess('Record Deleted Successfully.');
        } catch (\Exception $exception) {
            return redirect()->back()->withError($exception->getMessage());
        }
    }
}
