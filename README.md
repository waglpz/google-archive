# google-archive


`docker build --force-rm --build-arg APPUID=$(id -u) --build-arg APPUGID=$(id -g) --tag waglpz/gcloud-archiv .docker/`

`docker run --user $(id -u):$(id -g) --rm -ti -v $PWD:/app -v $PWD/.docker/ waglpz/gcloud-archiv bash`