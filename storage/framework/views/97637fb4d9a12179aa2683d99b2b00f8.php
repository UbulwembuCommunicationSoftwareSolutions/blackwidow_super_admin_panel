<div
    <?php echo e($attributes
            ->merge([
                'id' => $getId(),
            ], escape: false)
            ->merge($getExtraAttributes(), escape: false)); ?>

>
    <?php echo e($getChildSchema()); ?>

</div>
<?php /**PATH /Users/jacquestredoux/PhpstormProjects/blackwidow_super_admin_panel/vendor/filament/schemas/resources/views/components/grid.blade.php ENDPATH**/ ?>