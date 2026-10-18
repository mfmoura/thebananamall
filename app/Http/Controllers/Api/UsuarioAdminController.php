<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListUsuariosAdminRequest;
use App\Http\Requests\Api\StoreUsuarioAdminRequest;
use App\Http\Requests\Api\UpdateUsuarioAdminRequest;
use App\Http\Requests\Api\DeleteUsuarioAdminRequest;
use App\Http\Requests\Api\UpdateEmailUsuarioAdminRequest;
use App\Models\Usuario;
use App\Services\UsuarioAdminService;
use App\Services\UsuarioService;

class UsuarioAdminController extends Controller
{
    public function index(ListUsuariosAdminRequest $request, UsuarioAdminService $service)
    {
        return response()->json($service->listar($request->validated()));
    }

    public function show(Usuario $usuario, UsuarioAdminService $service)
    {
        return response()->json($service->consultar($usuario));
    }

    public function store(StoreUsuarioAdminRequest $request, UsuarioAdminService $service)
    {
        return response()->json($service->criar($request->validated()), 201);
    }

    public function update(UpdateUsuarioAdminRequest $request, Usuario $usuario, UsuarioAdminService $service)
    {
        return response()->json($service->atualizar($usuario, $request->validated()));
    }

    public function destroy(DeleteUsuarioAdminRequest $request, Usuario $usuario, UsuarioService $service)
    {
        $service->excluir($usuario);
        return response()->noContent();
    }

    public function email(UpdateEmailUsuarioAdminRequest $request, Usuario $usuario, UsuarioService $service)
    {
        $service->solicitarTrocaEmail($usuario, $request->validated('email'));
        return response()->json(['message' => 'Solicitação aceita. A troca depende da confirmação no novo e-mail.'], 202);
    }
}
