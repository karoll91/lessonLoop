import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Answers by "METHOD /path" and records every request; unknown routes fail
/// like a dead server. Routes can be changed between calls.
class RoutingAdapter implements HttpClientAdapter {
  RoutingAdapter(this.routes);

  final Map<String, (int, Object)> routes;
  final List<String> calls = [];
  final List<Object?> bodies = [];

  @override
  Future<ResponseBody> fetch(RequestOptions options, Stream<Uint8List>? requestStream, Future<void>? cancelFuture) async {
    final key = '${options.method} ${options.uri.path.replaceFirst('/api', '')}';
    calls.add(key);
    bodies.add(options.data);
    final route = routes[key];
    if (route == null) {
      throw DioException.connectionError(requestOptions: options, reason: 'refused');
    }
    return ResponseBody.fromString(
      jsonEncode(route.$2),
      route.$1,
      headers: {
        Headers.contentTypeHeader: ['application/json'],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
