<?php

namespace App\Telegram;

use App\Models\Answer;
use App\Models\Company;
use App\Models\PaycheckOrder;
use App\Models\PaymentMethod;
use App\Models\Project;
use DefStudio\Telegraph\Facades\Telegraph;
use DefStudio\Telegraph\Handlers\WebhookHandler;
use DefStudio\Telegraph\Keyboard\Button;
use DefStudio\Telegraph\Keyboard\Keyboard;
use DefStudio\Telegraph\Models\TelegraphChat;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Stringable;

class Handler extends WebhookHandler {

    public function start(){
        try {
            $message = "Привет, я телеграм бот для создания чеков! Для того чтобы отправить чек, поспользуйтесь командой /create и ответьте на все необходимые вопросы.";
            $this->chat->message($message)->send();
        } catch (\Exception $e){
            Log::info(json_encode($e, JSON_UNESCAPED_UNICODE));
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }

    public function create(){
        try {
            if($this->reject($this->chat))
                return;
            $orderId = random_int(100000, 999999);
            PaycheckOrder::create([
                'order_number' => $orderId,
                'chat_id' => $this->chat->chat_id,
                'username' => explode('] ',$this->chat->name)[1]
            ]);
            $buttons = [];
            $companies = Company::all();
            $allCompanyCount = count($companies->toArray());
            $startIndex = $this->data->get("start_index");
            $step = $allCompanyCount > $startIndex+8 ? 8 : $allCompanyCount-$startIndex;
            $companyCount = $startIndex ?? 0;
            foreach ($companies as $index => $company){
                $buttons[] = Button::make($company->title)
                    ->action('setCompany')
                    ->param('company', $company->id);
            }
            if($step == 8){
                $buttons[] = Button::make("Другая компания...")
                    ->action('send')
                    ->param('start_index', $companyCount+8);
            }
            $messageText = 'Выберите вашу компанию:';
            $log = Telegraph::chat($this->chat)
                ->message($messageText)
                ->keyboard(Keyboard::make()->buttons($buttons))
                ->send();
            Log::info($log);
        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }
//try{
//
//} catch (\Exception $e){
//    Log::info($e);
//    $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
//}
    private function createAnswer($chatId, $text, $questionId){
        $orderId = PaycheckOrder::query()->where('chat_id', $chatId)->where("send", false)->first()->id;
        Answer::query()->updateOrCreate(["order_id"=>$orderId, "question_id"=>$questionId],[
            'answer_text' => $text
        ]);
        return $orderId;
    }
    public function setCompany() {
        try{
//            Log::info($this->message->id());
//            if($this->reject($this->chat))
//                return;
            $companyName = Company::query()->where('id', $this->data->get('company'))->first()->title;
            $this->createAnswer($this->chat->chat_id, $companyName, 1);
            $this->chat->message('Компания '.$companyName)->silent()->send();
            $this->chat->message("Укажите компанию, в которой произведена оплата")->forceReply()->send();
        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }

    public function  setProject() {
        try{
//            Log::info($this->message->id());
//            if($this->reject($this->chat))
//                return;
            $project = Project::query()->where('id', $this->data->get('project'))->first()->title;
            $this->createAnswer($this->chat->chat_id, $project, 3);
            $this->chat->message('Проект: '.$project)->silent()->send();
            $this->chat->message("Укажите населенный пункт, где произведена оплата")->forceReply()->send();
        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }

    public function setPaymentMethod() {
        try{
//            Log::info($this->message->id());
//            if($this->reject($this->chat))
//                return;
            $paymentMethodId = $this->data->get('paymentMethod');
            $companyId = $this->data->get('company');
            $companyName = "";
            if($companyId)
                $companyName =Company::query()->where('id', $companyId)->first()->title;
            $paymentMethod = PaymentMethod::query()->where('id', $paymentMethodId)->first()->title.($companyId ? " (".$companyName.")" : "");
            $this->createAnswer($this->chat->chat_id, $paymentMethod, 5);
            if($paymentMethod === "Оплата с корпоративной карты" || $paymentMethod === "Снято с корпоративной карты"){
                $buttons = [];
                $companies = Company::all();
                $allCompanyCount = count($companies->toArray());
                $startIndex = $this->data->get("start_index");
                $step = $allCompanyCount > $startIndex+8 ? 8 : $allCompanyCount-$startIndex;
                $companyCount = $startIndex ?? 0;
                foreach ($companies as $index => $company){
                    $buttons[] = Button::make($company->title)
                        ->action('setPaymentMethod')
                        ->param('company', $company->id)->param("paymentMethod", $paymentMethodId);
                }
                if($step == 8){
                    $buttons[] = Button::make("Другая компания...")
                        ->action('sendCompany')
                        ->param('start_index', $companyCount+8);
                }
                $messageText = 'Укажите, с карты какой компании были сняты деньги:';
                $log = Telegraph::chat($this->chat)
                    ->message($messageText)
                    ->keyboard(Keyboard::make()->buttons($buttons))
                    ->send();
                Log::info($log);
                return;
            }
                $this->chat->message('Способ оплаты: '.$paymentMethod)->silent()->send();
                $this->chat->message("Укажите сумму оплаты")->forceReply()->send();

        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }

    public function setCompanyPayment() {
//        if($this->reject($this->chat))
//            return;
        $companyName = $this->data->get("company");
        $orderId = PaycheckOrder::query()->where('chat_id', $this->chat->chat_id)->first()->order_number;
        $answer = Answer::query()->where("order_id", $orderId)->where("question_id", 5)
            ->update(['answer_text', $companyName]);
        $this->chat->message("Способ оплаты: ".$companyName)->silent()->send();
        $this->chat->message("Укажите сумму оплаты")->forceReply()->send();
    }

    public function sendCompany() {
        $buttons = [];
        $companies = Company::all();
        $allCompanyCount = count($companies->toArray());
        $startIndex = $this->data->get("start_index");
        $step = $allCompanyCount > $startIndex+8 ? 8 : $allCompanyCount-$startIndex;
        $companyCount = $startIndex ?? 0;
        foreach ($companies as $index => $company){
            $buttons[] = Button::make($company->title)
                ->action('setCompanyPayment')
                ->param('company', $company->title);
        }
        if($step == 8){
            $buttons[] = Button::make("Другая компания...")
                ->action('sendCompany')
                ->param('start_index', $companyCount+8);
        }
        $messageText = 'Укажите, с карты какой компании были сняты деньги:';
        $log = Telegraph::chat($this->chat)
            ->message($messageText)
            ->keyboard(Keyboard::make()->buttons($buttons))
            ->send();
        Log::info($log);
    }

    public function preview() {
        try{
            $orderId = PaycheckOrder::query()->where('chat_id', $this->chat->chat_id)->first()->id;
            $answers = Answer::query()->where('order_id', $orderId)->orderBy("id")->get();

            $company = count($answers->where('question_id', 1))>0 ? ($answers->where('question_id', 1)->values())[0]->answer_text : null;
            $paycheckCompany = count($answers->where('question_id', 2))>0 ? ($answers->where('question_id', 2)->values())[0]->answer_text : null;
            $project = count($answers->where('question_id', 3))>0 ? ($answers->where('question_id', 3)->values())[0]->answer_text: null;
            $location = count($answers->where('question_id', 4))>0 ? ($answers->where('question_id', 4)->values())[0]->answer_text: null;
            $paymentMethod = count($answers->where('question_id', 5))>0 ? ($answers->where('question_id', 5)->values())[0]->answer_text: null;
            $sum = count($answers->where('question_id', 9))>0 ? ($answers->where('question_id', 9)->values())[0]->answer_text: null;
            $photo = count($answers->where('question_id', 10))>0 ? "Добавлено" : "Не добавлено";

            $comment = count($answers->where('question_id', 11))>0 ? ($answers->where('question_id', 11)->values())[0]->answer_text : "";

            $projects = null;
            if($company){
                Log::info($company);
                $companyId = Company::query()->where('title', $company)->first()->id;
                $projects = Project::query()->where('company_id', $companyId)->get();
            }

            $message = "Компания: ".($company ?: "")."\n" .
                "Организация, в которой произведена оплата: ".($paycheckCompany ?: "")."\n".
                ($projects ? "Проект: ".($project ?: "")."\n" : "").
                "Населенный пункт: ".($location ?: "")."\n".
                "Способ оплаты: ".($paymentMethod ?: "")."\n".
                "Сумма: ".($sum ?: "")."\n".
                "Фотография: ".$photo."\n".
                "Комментарий к трансакции: ".$comment."\n";
            $this->chat->message($message)->send();
        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
        }
    }

    public function send(){
        $order = PaycheckOrder::query()->where('chat_id', $this->chat->chat_id)->where('send', false)->first();
        if($order){
            $order->update(['send' => true]);
            $this->chat->message("Ваш чек успешно отправлен!")->send();
        } else
            $this->chat->message("Вы пока не создали чек. Для создания воспользуйтесь командой /create")->send();

    }

    public function cancel() {
        $order = PaycheckOrder::query()->where('chat_id', $this->chat->chat_id)->where('send', false)->first();
        if($order){
            $order->delete();
            Answer::query()->where('order_id', $order->id)->delete();
            $this->chat->message("Создание чека отменено. Для создания нового воспользуйтесь командой /create")->send();
        } else
            $this->chat->message("Вы пока не создали чек. Для создания воспользуйтесь командой /create")->send();
    }

    private function reject(TelegraphChat $chat){
        if(count(PaycheckOrder::query()->where('chat_id', $chat->chat_id)->where('send', false)->get())>0){
            $chat->message("Вы не закончили создание предыдущего чека. Напишите сообщение в ответ на последний заданный вопрос или отмените создание, с помощью команды /cancel")
            ->send();
            return true;
        } else
            return false;
    }

    public function handleChatMessage(Stringable $text): void {
        try{
            Log::info($this->message);
            if($this->message->replyToMessage()){
                if($this->message->replyToMessage()->text() == "Укажите компанию, в которой произведена оплата"){
                    $orderId = $this->createAnswer($this->chat->chat_id, $this->message->text(), 2);
                    $companyName = Answer::query()->where('order_id', $orderId)
                        ->where('question_id', 1)->first()->answer_text;
                    $companyId = Company::query()->where('title', $companyName)->first()->id;
                    $projects = Project::query()->where('company_id', $companyId)->get();
                    if(count($projects)>0){
                        $buttons = [];
                        foreach ($projects as $index => $project){
                            $buttons[] = Button::make($project->title)
                                ->action('setProject')
                                ->param('project', $project->id);
                        }
                        $messageText = 'Укажите проект, по которому произведена оплата:';
                        Log::info($buttons);
                        $log = Telegraph::chat($this->chat)
                            ->message($messageText)
                            ->keyboard(Keyboard::make()->buttons($buttons))
                            ->send();
                    } else {
                        $this->chat->message("Укажите населенный пункт, где произведена оплата")->forceReply()->send();
                    }

                }
                if($this->message->replyToMessage()->text() == "Укажите населенный пункт, где произведена оплата"){
                    $orderId = $this->createAnswer($this->chat->chat_id, $this->message->text(), 4);
                    $paymentMethods = PaymentMethod::all();
                    $buttons = [];
                foreach ($paymentMethods as $index => $paymentMethod){
                    Log::info($paymentMethod->title);
                    $buttons[] = Button::make($paymentMethod->title)
                        ->action('setPaymentMethod')
                        ->param('paymentMethod', $paymentMethod->id);
                }
                    $messageText = 'Выберите способ оплаты:';
                    $log = Telegraph::chat($this->chat)
                        ->message($messageText)
                        ->keyboard(Keyboard::make()->buttons($buttons))->send();
//                        ->keyboard(Keyboard::make()->buttons([
//                            Button::make("Наличные")->action('setPaymentMethod')->param('paymentMethod', "Наличные"),
//                            Button::make("Оплата с корпоративной карты")->action('setPaymentMethod')->param('paymentMethod', ["Оплата с корпоративной карты" , "efwf"]),
//                            Button::make("Снято с корпоративной карты")->action('setPaymentMethod')->param('paymentMethod', "Снято с корпоративной карты"),
//                            Button::make("Наличные собственные")->action('setPaymentMethod')->param('paymentMethod', "Наличные собственные"),
//                        ]);
                }
                if($this->message->replyToMessage()->text() == "Укажите сумму оплаты"){
                    $orderId = $this->createAnswer($this->chat->chat_id, $this->message->text(), 9);
                    $this->chat->message("Добавьте фотографию, подтверждающую оплату (чек, скрин перевода и т.п.)")->forceReply()->send();
                }
                if($this->message->replyToMessage()->text() == "Добавьте фотографию, подтверждающую оплату (чек, скрин перевода и т.п.)"){
                    Log::info($this->message->toArray()['photos'][2]['id']);
                    $client = new Client([
                       'base_uri' =>  'https://api.telegram.org/bot' . env("TELEGRAM_BOT_TOKEN") . '/',
                    ]);
                    $response = $client->get('getFile', [
                        'query' => [
                            'file_id' => $this->message->toArray()['photos'][2]['id'],
                        ],
                    ]);
                    $data = json_decode($response->getBody(), true);
                    Log::info($data);
                    if($data['ok']){
                        $client = new Client();
                        $url ="https://api.telegram.org/file/bot".env("telegram_bot_token")."/".$data['result']['file_path'];
                        $response = $client->get($url);

                        $fileName = basename($url);

                        $path = Storage::disk('public')->put($fileName, $response->getBody());
                        $this->createAnswer($this->chat->chat_id, $fileName, 10);
                    }
                   else
                        $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();
                   $this->chat->message("Укажите комментарий к транзакции:")->forceReply()->send();
                }
                if($this->message->replyToMessage()->text() == "Укажите комментарий к транзакции:"){
                    $orderId = $this->createAnswer($this->chat->chat_id, $this->message->text(), 11);
                    $this->chat->message("Проверьте введенные данные: ")->silent()->send();
                    $this->preview($orderId);
                }
            }

        } catch (\Exception $e){
            Log::info($e);
            $this->chat->message("Произошла ошибка, попробуйте повторить позже")->send();

        }
    }
}
