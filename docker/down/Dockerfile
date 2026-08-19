# Mediadev Monitor — down: servicio sin listener HTTP.
#
# Emula un sitio caído (RF-11): no expone ningún puerto, por lo que cualquier
# probe HTTP produce HTTP 000 (connection refused). `sleep infinity` mantiene
# el contenedor "running" para que compose no lo considere exitoso como error.

FROM alpine:3.19

CMD ["sleep", "infinity"]
