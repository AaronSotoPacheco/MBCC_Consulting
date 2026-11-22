FROM mysql:8.4
USER root
RUN microdnf install -y tar gzip findutils && microdnf clean all
COPY respaldar.sh restaurar.sh ciclo.sh /usr/local/bin/
RUN sed -i 's/\r$//' /usr/local/bin/respaldar.sh /usr/local/bin/restaurar.sh /usr/local/bin/ciclo.sh \
    && chmod +x /usr/local/bin/respaldar.sh /usr/local/bin/restaurar.sh /usr/local/bin/ciclo.sh
ENTRYPOINT ["/usr/local/bin/ciclo.sh"]
