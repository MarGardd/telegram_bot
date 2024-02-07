<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Company;
use App\Models\PaycheckOrder;
use App\Models\PaymentMethod;
use App\Models\Project;
use Google\Service\Drive;
use Google\Service\Drive\Permission;
use Google\Service\Sheets;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\ValueRange;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    public function index(Request $request){
        $archive = false;
        if ($request->input('archive'))
            $archive = $request->input('archive');
            
        $orders = PaycheckOrder::where('deleted', false)
                                ->where('archive', $archive)
                                ->where('send', true)
                                ->when(filter_var($request->input('checked'), FILTER_VALIDATE_BOOLEAN), function($query) use ($request){
                                    $query->where('checked', filter_var($request->input('checked'), FILTER_VALIDATE_BOOLEAN));            
                                })
                                // ->when(filter_var($request->input('archive'), FILTER_VALIDATE_BOOLEAN), function($query) use ($request){
                                //     $query->where('archive', filter_var($request->input('archive'), FILTER_VALIDATE_BOOLEAN));            
                                // })
                                // ->when($request->input('date'), function($query) use ($request){
                                //     $query->orderBy('created_at', $request->input('date'));
                                // })
                                ->when($request->input('min_date'), function($query) use ($request){
                                    return $query->whereDate('created_at', '>=', $request->input('min_date'));
                                })
                                ->when($request->input('max_date'), function($query) use ($request){
                                    return $query->whereDate('created_at', '<=', $request->input('max_date'));
                                })
                                ->get();

        $result = [];
        foreach($orders as $order){
            $result[$order->id]['id'] = $order->id;
            $result[$order->id]['username'] = $order->username;
            $result[$order->id]['date'] = $order->created_at;
            $result[$order->id]['status'] = $order->status;
            $result[$order->id]['checked'] = $order->checked;
            $result[$order->id]['archive'] = $order->archive;

            $answers = Answer::where('order_id', $order->id)->get();
            foreach($answers as $answer){                
                switch($answer->question_id){
                    case 1: 
                        $result[$order->id]['company'] = $answer->answer_text;
                        break;
                    case 2:
                        $result[$order->id]['organization'] = $answer->answer_text;
                        break;
                    case 3:
                        $result[$order->id]['project'] = $answer->answer_text;
                        break;
                    case 4:
                        $result[$order->id]['locality'] = $answer->answer_text;
                        break;
                    case 5:
                        $result[$order->id]['payment_method'] = $answer->answer_text;
                        break;
                    case 6:
                        $result[$order->id]['sum'] = $answer->answer_text;
                        break;
                    case 7:
                        $result[$order->id]['file'] = url('storage/' . $answer->answer_text);
                        break;
                    case 8:
                        $result[$order->id]['comment'] = $answer->answer_text;
                        break;
                }
            }
        }
        
        return collect($result)
                    ->when($request->input('sum'), function($query) use ($request){
                        return $query->sortBy('sum', SORT_REGULAR, filter_var($request->input('sum'), FILTER_VALIDATE_BOOLEAN));
                    })
                    ->when($request->input('date'), function($query) use ($request){
                        return $query->sortBy('date', SORT_REGULAR, filter_var($request->input('date'), FILTER_VALIDATE_BOOLEAN));
                    })
                    // ->when($request->input('min_date'), function($query) use ($request){
                    //     return $query->where('date', '>=', $request->input('min_date'));
                    // })
                    // ->when($request->input('max_date'), function($query) use ($request){
                    //     return $query->where('date', '<=', $request->input('max_date'));
                    // })
                    ->values();
    }

    public function store(Request $request){
        PaycheckOrder::create([
            'order_number' => random_int(100000, 999999),
            'chat_id' => $request->input('chat_id'),
            'username' => $request->input('username'),
        ]);
    }

    public function checked($order_id){
        $order = PaycheckOrder::find($order_id);
        $order->checked = true;
        $order->save();
    }

    public function archive($order_id){
        $order = PaycheckOrder::find($order_id);
        $order->archive = true;
        $order->save();
    }

    public function delete($order_id){
        $order = PaycheckOrder::find($order_id);
        $order->deleted = true;
        $order->save();
    }

    public function getCompanies(){
        return Company::with('projects:id,title,company_id')->get(['id', 'title']);
    }

    public function addCompany(Request $request){
        Company::create([
            'title' => $request->input('title'),
        ]);
    }

    public function updateCompany(Request $request, $company_id){
        $company = Company::find($company_id);
        $company->title = $request->input('title') ?? $company->title;
        $company->save();
    }

    public function deleteCompany($company_id){
        Company::find($company_id)->delete();
    }

    public function getProjects($company_id){
        return Project::where('company_id', $company_id)->get(['id', 'title']);
    }

    public function addProject(Request $request, $company_id){
        Project::create([
            'company_id' => $company_id,
            'title' => $request->input('title'),
        ]);
    }

    public function updateProject(Request $request, $company_id, $project_id){
        $project = Project::find($project_id);
        $project->title = $request->input('title') ?? $project->title;
        $project->save();
    }

    public function deleteProject($company_id, $project_id){
        Project::find($project_id)->delete();
    }

    public function getPaymentMethods(){
        return PaymentMethod::all(['id', 'title']);
    }

    public function addPaymentMethod(Request $request){
        PaymentMethod::create([
            'title' => $request->input('title'),
            'has_companies' => $request->input('has_companies'),
        ]);
    }

    public function updatePaymentMethod(Request $request, $payment_method){
        $company = PaymentMethod::find($payment_method);
        $company->title = $request->input('title') ?? $company->title;
        $company->save();
    }

    public function deletePaymentMethod($payment_method){
        PaymentMethod::find($payment_method)->delete();
    }

    public function sheet(){
        $orders = PaycheckOrder::where('deleted', false)
                                ->where('archive', false)
                                ->where('send', true)
                                ->get();

        $result = [];
        foreach($orders as $order){
            $result[0] = ['Пользователь', 'Дата', 'Компания', 'Организация', 'Проект', 'Населённый пункт', 'Способ оплаты', 'Сумма', 'Комментарий'];
            $result[$order->id][0] = $order->username;
            $result[$order->id][1] = $order->created_at;

            $answers = Answer::where('order_id', $order->id)->get();
            foreach($answers as $answer){                
                switch($answer->question_id){
                    case 1: 
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 2:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 3:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 4:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 5:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 6:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                    case 8:
                        $result[$order->id][$answer->question_id+2] = $answer->answer_text;
                        break;
                }
            }
            ksort($result[$order->id]);
            $result[$order->id] = array_values($result[$order->id]);
        }

        putenv('GOOGLE_APPLICATION_CREDENTIALS='.storage_path('service_key.json'));

        $client = new Google_Client();
        $client->useApplicationDefaultCredentials();
        $client->addScope(['https://www.googleapis.com/auth/drive', 'https://www.googleapis.com/auth/spreadsheets']);
        
        $service = new Sheets($client);

        $SpreadsheetProperties = new SpreadsheetProperties();
        $SpreadsheetProperties->setTitle('NewSpreadsheet');
        $Spreadsheet = new Spreadsheet();
        $Spreadsheet->setProperties($SpreadsheetProperties);
        $response = $service->spreadsheets->create($Spreadsheet);
        
        $Drive = new Drive($client);
        $DrivePermisson = new Permission();
        $DrivePermisson->setType('user');
        $DrivePermisson->setEmailAddress('informatika.1827@gmail.com');
        $DrivePermisson->setRole('writer');
        $Drive->permissions->create($response->spreadsheetId, $DrivePermisson);

        $range = 'Sheet1!A1:Z';
        $ValueRange = new ValueRange();
        $ValueRange->setValues(array_values($result));
        $options = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->append($response->spreadsheetId, $range, $ValueRange, $options);
        
        return $response->spreadsheetUrl;
    }
}
