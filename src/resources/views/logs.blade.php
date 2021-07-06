@extends(backpack_view('layouts.top_left'))

@php
  $breadcrumbs = [
    trans('backpack::crud.admin') => backpack_url('dashboard'),
    trans('romansev::logmanager.log_manager') => backpack_url('log'),
    trans('romansev::logmanager.existing_logs') => false,
  ];
@endphp

@section('header')
    <section class="container-fluid">
      <h2>
        {{ trans('romansev::logmanager.log_manager') }}<small>{{ trans('romansev::logmanager.log_manager_description') }}</small>
      </h2>
    </section>
@endsection


@section('content')
    @foreach ($allFiles as $key => $files)
        <!-- Default box -->
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover table-condensed pb-0 mb-0">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ trans('romansev::logmanager.file_name') }}</th>
                        <th>{{ trans('romansev::logmanager.last_modified') }}</th>
                        <th class="text-right">{{ trans('romansev::logmanager.file_size') }}</th>
                        <th>{{ trans('romansev::logmanager.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($files as $key => $file)
                        <tr>
                            <th scope="row">{{ $key + 1 }}</th>
                            <td>{{ $file['file_name'] }}</td>
                            <td>{{ \Carbon\Carbon::createFromTimeStamp($file['last_modified'])->formatLocalized('%H:%M') }}</td>
                            <td class="text-right">{{ round((int)$file['file_size']/1048576, 2).' MB' }}</td>
                            <td>
                                <a class="btn btn-sm btn-link" href="{{ url(config('backpack.base.route_prefix', 'admin').'/log/preview/'. base64_encode($file['orig_name'])) }}"><i class="la la-eye"></i> {{ trans('romansev::logmanager.preview') }}</a>
                                <a class="btn btn-sm btn-link" href="{{ url(config('backpack.base.route_prefix', 'admin').'/log/download/'.base64_encode($file['orig_name'])) }}"><i class="la la-cloud-download"></i> {{ trans('romansev::logmanager.download') }}</a>
                                @if (config('backpack.logmanager.allow_delete'))
                                    <a class="btn btn-sm btn-link" data-button-type="delete" href="{{ url(config('backpack.base.route_prefix', 'admin').'/log/delete/'.base64_encode($file['orig_name'])) }}"><i class="la la-trash-o"></i> {{ trans('romansev::logmanager.delete') }}</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>

            </div><!-- /.box-body -->
        </div><!-- /.box -->
    @endforeach
@endsection

@section('after_scripts')
<script>
  jQuery(document).ready(function($) {

    // capture the delete button
    $("[data-button-type=delete]").click(function(e) {
        e.preventDefault();
        var delete_button = $(this);
        var delete_url = $(this).attr('href');

        if (confirm("{{ trans('romansev::logmanager.delete_confirm') }}") == true) {
            $.ajax({
                url: delete_url,
                type: 'DELETE',
                data: {
                  _token: "<?php echo csrf_token(); ?>"
                },
                success: function(result) {
                    // delete the row from the table
                    delete_button.parentsUntil('tr').parent().remove();

                    // Show an alert with the result
                    new Noty({
                        text: "<strong>{{ trans('romansev::logmanager.delete_confirmation_title') }}</strong><br>{{ trans('romansev::logmanager.delete_confirmation_message') }}",
                        type: "success"
                    }).show();
                },
                error: function(result) {
                    // Show an alert with the result
                    new Noty({
                        text: "<strong>{{ trans('romansev::logmanager.delete_error_title') }}</strong><br>{{ trans('romansev::logmanager.delete_error_message') }}",
                        type: "warning"
                    }).show();
                }
            });
        } else {
            new Noty({
                text: "<strong>{{ trans('romansev::logmanager.delete_cancel_title') }}</strong><br>{{ trans('romansev::logmanager.delete_cancel_message') }}",
                type: "info"
            }).show();
        }
      });

  });
</script>
@endsection
