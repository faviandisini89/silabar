<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return view('notifications', ['items' => $request->user()->notifications()->paginate(25)]);
    }

    public function open(Request $request, string $id)
    {
        $n = $request->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        $route = $n->data['route'] ?? null;

        return redirect()->route($route && \Illuminate\Support\Facades\Route::has($route) ? $route : 'notifications.index');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }
}
