<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('articles')->orderBy('name')->get();
        return view('admin.categories.index', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:categories',
            'color'       => 'required|string|max:20',
            'icon'        => ['nullable', 'string', 'regex:/^fa-[a-z0-9-]{1,40}$/'],
            'description' => 'nullable|string|max:500',
        ]);
        $data['slug'] = Str::slug($data['name']);
        $category = Category::create($data);
        ActivityLog::record('category.created', $category, 'Created category "' . $category->name . '"');
        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:categories,name,'.$category->id,
            'color'       => 'required|string|max:20',
            'icon'        => ['nullable', 'string', 'regex:/^fa-[a-z0-9-]{1,40}$/'],
            'description' => 'nullable|string|max:500',
        ]);
        $category->update($data);
        ActivityLog::record('category.updated', $category, 'Updated category "' . $category->name . '"');
        return back()->with('success', 'Category updated.');
    }

    public function destroy(Category $category)
    {
        if ($category->articles()->count() > 0) {
            return back()->with('error', "Cannot delete: category has {$category->articles()->count()} articles. Reassign them first.");
        }
        $name = $category->name;
        $category->delete();
        ActivityLog::record('category.deleted', null, 'Deleted category "' . $name . '"');
        return back()->with('success', 'Category deleted.');
    }
}
