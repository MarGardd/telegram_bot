<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Company;
use App\Models\PaycheckOrder;
use App\Models\PaycheckOrderFile;
use App\Models\PaymentMethod;
use App\Models\Project;
use App\Models\User;
use Google\Service\Drive;
use Google\Service\Drive\Permission;
use Google\Service\Sheets;
use Google\Service\Sheets\Spreadsheet;
use Google\Service\Sheets\SpreadsheetProperties;
use Google\Service\Sheets\ValueRange;
use Google_Client;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;

class Controller extends BaseController
{
    public function index(Request $request){
        $archive = false;
        if ($request->input('archive'))
            $archive = $request->input('archive');

        $orders = PaycheckOrder::with('files')
                                ->where('deleted', false)
                                ->where('archive', $archive)
                                ->where('send', true)

                                ->get();

        $result = [];
        foreach($orders as $order){
            $result[$order->id]['id'] = $order->id;
            $result[$order->id]['username'] = $order->username;
            $result[$order->id]['files'] = $order->files;
            $result[$order->id]['files'][] = ["path"=>null];
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
                        $result[$order->id]['pay_date'] = $answer->answer_text;
                        break;
                    case 9:
                        $result[$order->id]['comment'] = $answer->answer_text;
                        break;
                }
            }
        }

        return collect($result)
            ->when($request->input('sum'), function($query) use ($request){
                return $query->sortBy('sum', SORT_REGULAR, filter_var($request->input('sum'), FILTER_VALIDATE_BOOLEAN));
            })
            ->when($request->input('sort'), function($query) use ($request){
                return $query->sortBy($request->input('sort'));
            })
//            ->when($request->input('date'), function($query) use ($request){
//                return $query->sortBy('date', SORT_REGULAR, filter_var($request->input('date'), FILTER_VALIDATE_BOOLEAN));
//            })
            // ->when($request->input('min_date'), function($query) use ($request){
            //     return $query->where('date', '>=', $request->input('min_date'));
            // })
            // ->when($request->input('max_date'), function($query) use ($request){
            //     return $query->where('date', '<=', $request->input('max_date'));
            // })
//            ->sortBy('date')
            ->values();
    }

    public function store(Request $request){
        PaycheckOrder::create([
            'order_number' => random_int(100000, 999999),
            'chat_id' => $request->input('chat_id'),
            'username' => $request->input('username'),
        ]);
    }

    public function update(Request $request, $order_id){
        $answer = Answer::where('order_id', $order_id)->where('question_id', $request->input('question_id'))->get();
        $answer[0]->answer_text = $request->input('answer_text') ?? $answer->answer_text;
        $answer[0]->save();
    }

    public function addPhoto(Request $request, $order_id){
        if($request->hasFile('file')){
            $file = $request->file('file');
            $path = $file->storeAs('', $file->hashName(), 'public');
            PaycheckOrderFile::create([
                'order_id' => $order_id,
                'path' => $path
            ]);
        }
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

    public function restore($order_id){
        $order = PaycheckOrder::find($order_id);
        $order->archive = false;
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
            ->where('checked', true)
            ->get();

        $result = [];
        foreach($orders as $order){
            $result[0] = ['Пользователь', 'Компания', 'Организация', 'Проект', 'Населённый пункт', 'Способ оплаты', 'Сумма', 'Дата', 'Комментарий'];
            $result[$order->id][0] = $order->username;
//            $result[$order->id][1] = $order->created_at;

            $answers = Answer::where('order_id', $order->id)->get();
            foreach($answers as $answer){
                switch($answer->question_id){
                    case 1:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 2:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 3:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 4:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 5:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 6:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 7:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
                        break;
                    case 9:
                        $result[$order->id][$answer->question_id] = $answer->answer_text;
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
        $DrivePermisson->setEmailAddress('aaverbitskiy@gmail.com');
        $DrivePermisson->setRole('writer');
        $Drive->permissions->create($response->spreadsheetId, $DrivePermisson);

        $Drive = new Drive($client);
        $DrivePermisson = new Permission();
        $DrivePermisson->setType('user');
        $DrivePermisson->setEmailAddress('izharbolit@gmail.com');
        $DrivePermisson->setRole('writer');
        $Drive->permissions->create($response->spreadsheetId, $DrivePermisson);

        $Drive = new Drive($client);
        $DrivePermisson = new Permission();
        $DrivePermisson->setType('user');
        $DrivePermisson->setEmailAddress('chubarkint@gmail.com');
        $DrivePermisson->setRole('writer');
        $Drive->permissions->create($response->spreadsheetId, $DrivePermisson);

        $range = 'Sheet1!A1:Z';
        $ValueRange = new ValueRange();
        $ValueRange->setValues(array_values($result));
        $options = ['valueInputOption' => 'USER_ENTERED'];
        $service->spreadsheets_values->append($response->spreadsheetId, $range, $ValueRange, $options);

        return $response->spreadsheetUrl;
    }

    public function filter(Request $request, PaycheckOrder $orders){
        $orders->when(filter_var($request->input('checked'), FILTER_VALIDATE_BOOLEAN), function($query) use ($request){
            $query->where('checked', filter_var($request->input('checked'), FILTER_VALIDATE_BOOLEAN));
        })
        // ->when(filter_var($request->input('archive'), FILTER_VALIDATE_BOOLEAN), function($query) use ($request){
        //     $query->where('archive', filter_var($request->input('archive'), FILTER_VALIDATE_BOOLEAN));
        // })
        ->when($request->input('date'), function($query) use ($request){
            $query->orderBy('created_at', $request->input('date'));
        })
        ->when($request->input('min_date'), function($query) use ($request){
            return $query->whereDate('created_at', '>=', $request->input('min_date'));
        })
        ->when($request->input('max_date'), function($query) use ($request){
            return $query->whereDate('created_at', '<=', $request->input('max_date'));
        })
        ->when($request->input('company'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 1)->where('answer_text', $request->input('company'));
            });
        })
        ->when($request->input('organization'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 2)->where('answer_text', 'ilike', '%' . $request->input('organization') . '%');
            });
        })
        ->when($request->input('project'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 3)->where('answer_text', $request->input('project'));
            });
        })
        ->when($request->input('locality'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 4)->where('answer_text', 'ilike', '%' . $request->input('locality') . '%');
            });
        })
        ->when($request->input('payment_method'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 5)->where('answer_text', $request->input('payment_method'));
            });
        })
        ->when($request->input('min_sum'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 6)->whereRaw('CAST(answer_text as decimal) >= ?', [(int)$request->input('min_sum')]);
            });
        })
        ->when($request->input('max_sum'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 6)->whereRaw('CAST(answer_text as decimal) <= ?', [(int)$request->input('max_sum')]);
            });
        })
        ->when($request->input('min_pay_date'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 7)->whereDate('answer_text', '>=', date($request->input('min_pay_date')));
            });
        })
        ->when($request->input('max_pay_date'), function($query) use ($request){
            return $query->whereHas('answers', function ($query) use ($request){
                $query->where('question_id', 7)->whereDate('answer_text', '<=', date($request->input('max_pay_date')));
            });
        });

        return $orders;
    }

    public function getUsers(){
        return User::orderByDesc('deleted')->orderBy('name')->get(['id', 'name', 'email']);
    }

    public function createUser(Request $request)
    {
        User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => Hash::make($request->input('password')),
        ]);
    }

    public function deleteUser($user_id){
        $user = User::find($user_id);
        $user->deleted = true;
        $user->save();
    }

    public function recoverUser($user_id){
        $user = User::find($user_id);
        $user->deleted = false;
        $user->save();
    }

    public function setAdmin($user_id){
        $user = User::find($user_id);
        $user->is_admin = true;
        $user->save();
    }

    public function deleteAdmin($user_id){
        $user = User::find($user_id);
        $user->is_admin = false;
        $user->save();
    }
}
