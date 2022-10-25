<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class TestMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public $model;
    
    public function __construct($model)
    {
        $this->model = $model;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $this->subject($this->model->subject);

        foreach ($this->model->reports as $file){
            $this->attach(storage_path('app/' . $file));
        }

        return $this->view('emails.test')->with(["body" => $this->model->body]);
    }
}
