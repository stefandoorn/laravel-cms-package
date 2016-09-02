<?php
/**
 * Created by PhpStorm.
 * User: nielsfilmer
 * Date: 05/07/16
 * Time: 16:17
 */

namespace NielsFilmer\CmsPackage;


use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Route;
use Kris\LaravelFormBuilder\Form;
use Kris\LaravelFormBuilder\FormBuilder;
use NielsFilmer\EloquentLister\ListBuilder;
use NielsFilmer\EloquentLister\TableList;

abstract class CmsController extends Controller
{
    /**
     * @var int
     */
    protected $default_rpp = 50;

    /**
     * @var string
     */
    protected $index_heading;

    /**
     * @var array
     */
    protected $breadcrumbs = [
        'create' => [
            'New object' => null
        ],
        'edit' => [
            'Edit object' => null,
        ],
    ];

    /**
     * @var string
     */
    protected $index_view = "cms-package::default-resources.list";

    /**
     * @var string
     */
    protected $index_filter;

    /**
     * @var string
     */
    protected $class;

    /**
     * @var TableList
     */
    protected $list_class;

    /**
     * @var Form
     */
    protected $form_class;

    /**
     * @var string
     */
    protected $form_view = 'cms-package::default-resources.form';

    /**
     * @var string
     */
    protected $display_attribute = "name";

    /**
     * @var bool
     */
    protected $show_add = true;

    /**
     * @var string|null
     */
    protected $slug;

    /**
     * @var string
     */
    protected $object_name = 'Object';

    /**
     * @var string
     */
    protected $default_order = 'created_at';

    /**
     * @var string
     */
    protected $default_order_direction = 'desc';


    /**
     * Constructor
     */
    public function __construct()
    {

    }


    /**
     * @param Request $request
     *
     * @return array
     */
    protected function getOrder(Request $request)
    {
        return $request->get('order') ? explode('|',$request->get('order')) : [
            $this->default_order,
            $this->default_order_direction
        ];
    }


    /**
     * @param Request $request
     *
     * @return int
     */
    protected function getRpp(Request $request)
    {
        return $request->get('rpp') ?: $this->default_rpp;
    }


    /**
     * @param Request $request
     *
     * @return mixed
     */
    protected function getListQuery(Request $request, $args = [])
    {
        $order = $this->getOrder($request);
        $rpp = $this->getRpp($request);
        $class = $this->class;

        return $class::orderBy($order[0], $order[1])->paginate($rpp);
    }


    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $request = app(Request::class);
        $listbuilder = app(ListBuilder::class);
        $args = func_get_args();

        $slug = (empty($this->slug)) ? substr($request->getPathInfo(), 1) : $this->slug;

        $list = $listbuilder->build(new $this->list_class, $this->getListQuery($request, $args), [
            'show_action' => false,
            'slug' => $slug,
        ]);
        $filter = $this->index_filter;
        $show_add = $this->show_add;

        if(method_exists($this, 'getIndexBreadcrumb')) {
            $heading = $this->getIndexBreadcrumb($request, $args);
        } else {
            $heading = $this->index_heading;
        }

        $object_name = $this->object_name;

        return view($this->index_view, compact('list', 'heading', 'filter', 'show_add', 'args', 'object_name'));
    }


    /**
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function create()
    {
        $request = app(Request::class);
        $formbuilder = app(FormBuilder::class);
        $args = func_get_args();
        $referer = url()->previous();

        $route = Route::getCurrentRoute()->getName();

        if(method_exists($this, 'getCreateFormData')) {
            $form_data = $this->getCreateFormData($request, $args);
            $form_data = array_merge(['prev_url' => $referer], $form_data);
        } else {
            $form_data = ['prev_url' => $referer];
        }

        $url = (empty($this->route_store)) ? route(str_replace('create', 'store', $route)) : $this->route_store;

        $form = $formbuilder->create($this->form_class, [
            'method' => 'POST',
            'url' => $url,
            'data' => $form_data,
        ]);

        if(method_exists($this, 'getCreateBreadcrumb')) {
            $breadcrumb = $this->getCreateBreadcrumb($request, $args);
        } else {
            $breadcrumb = $this->breadcrumbs['create'];
        }

        return view($this->form_view, compact('form', 'breadcrumb', 'args'));
    }


    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function edit()
    {
        $request = app(Request::class);
        $formbuilder = app(FormBuilder::class);
        $args = func_get_args();
        $id = $args[0];

        $referer = url()->previous();
        $route = Route::getCurrentRoute()->getName();

        if(method_exists($this, 'getEditModel')) {
            $model = $this->getEditModel($request, $id, $args);
        } else {
            $class = $this->class;
            $model = $class::findOrFail($id);
        }

        if(method_exists($this, 'getEditFormData')) {
            $form_data = $this->getEditFormData($model, $request, $args);
            $form_data = array_merge(['prev_url' => $referer], $form_data);
        } else {
            $form_data = ['prev_url' => $referer];
        }

        $url = (empty($this->route_edit)) ? route(str_replace('edit', 'update', $route), $id) : $this->route_edit;

        $form = $formbuilder->create($this->form_class, [
            'method' => 'PUT',
            'url' => $url,
            'data' => $form_data,
            'model' => $model,
        ]);

        if(method_exists($this, 'getEditBreadcrumb')) {
            $breadcrumb = $this->getEditBreadcrumb($model, $request, $args);
        } else {
            $breadcrumb = $this->breadcrumbs['edit'];
        }

        return view($this->form_view, compact('form', 'breadcrumb', 'model', 'args'));
    }


    /**
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy()
    {
        $id = func_get_arg(0);

        $class = $this->class;
        $model = $class::findOrFail($id);
        $name = $model->{$this->display_attribute};
        $route = Route::getCurrentRoute()->getName();
        $model->delete();

        flash()->success("{$name} was removed");
        return redirect()->route(str_replace('destroy', 'index', $route));
    }
}