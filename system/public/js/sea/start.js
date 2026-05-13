switch (table_type) {
    case 'reseved':
        loadReceived()    
    break;
    case 'inside':
        loadinside()    
    break;
    case 'outside':
        loadoutside()    
    break;
    case 'containers':
        loadcontainers()    
    break;
    case 'canceled_containers':
        loadCanceledcontainers()    
    break;
    case 'canceled':
        loadcanceled()    
    break;
}