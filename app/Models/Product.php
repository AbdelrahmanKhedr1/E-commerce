<?php

namespace App\Models;

// use Illuminate\Contracts\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
class Product extends Model
{
    use HasFactory;
    protected $fillable = [
        'store_id',
        'category_id',
        'name',
        'slug',
        'description',
        'image',
        'price',
        'compare_price',
        'options',
        'rating',
        'featured',
        'status',
        'quantity',

    ];

    protected $hidden  = ['created_at','updated_at']; // عشان مبعتش دول مع ال api
    protected $appends = ['image_url'];

    // دي الاكواد اللي عاوزها تشتغل كل مره هستخدم فيها الموديل
    protected static function booted()
    {
        static::addGlobalScope('store', function(Builder $builder){
            $user = Auth::user();
            if ($user && $user->store_id) {
            $builder->where('store_id',$user->store_id);
            }
        });
        static::creating(function(Product $product) {
            $product->slug = Str::slug($product->name);
        });
    }

    public function category(){
        return $this->belongsTo(Category::class,'category_id');
    }
    public function store(){
        return $this->belongsTo(Store::class,'store_id');
    }

    public function tags(){
        return $this->belongsToMany(
            Tag::class,     // Related Model
            'product_tag',  // Pivot table name
            'product_id',   // FK in pivot table for the current model
            'tag_id',       // FK in pivot table for the related model
            'id',           // PK current model
            'id'            // PK related model
        );
    }

    public function scopeActive(Builder $builder)
    {
        return $builder->where('status','active');
    }

    public function getImageUrlAttribute(){
        if(!$this->image){
            return  asset('assets/images/products/comingsoon.png') ;
        }
        if(Str::startsWith($this->image,['https://','http://'])){
            return $this->image;
        }
        return Storage::url($this->image);
    }

    public function getSalePercentAttribute(){
        if(!$this->compare_price){
            return 0;
        }
        return number_format( 100 - ( 100 * $this->price / $this->compare_price),1);
    }


    public function scopeFilter(Builder $builder, $filters)
    {
        $options = array_merge([
            'store_id' => null,
            'category_id' => null,
            'tag_id' => null,
            'status' => 'active',
        ], $filters);

        $builder->when($options['status'], function ($query, $status) {
            return $query->where('status', $status);
        });

        $builder->when($options['store_id'], function($builder, $value) {
            $builder->where('store_id', $value);
        });
        $builder->when($options['category_id'], function($builder, $value) {
            $builder->where('category_id', $value);
        });
        $builder->when($options['tag_id'], function($builder, $value) {

            $builder->whereExists(function($query) use ($value) {
                $query->select(1)
                    ->from('product_tag')
                    ->whereRaw('product_id = products.id')
                    ->where('tag_id', $value);
            });
            // $builder->whereRaw('id IN (SELECT product_id FROM product_tag WHERE tag_id = ?)', [$value]);
            // $builder->whereRaw('EXISTS (SELECT 1 FROM product_tag WHERE tag_id = ? AND product_id = products.id)', [$value]);

            // $builder->whereHas('tags', function($builder) use ($value) {
            //     $builder->where('id', $value);
            // });
        });
    }
}
