import React, { useContext, useEffect } from 'react'
import './Verify.css'
import { useNavigate, useSearchParams } from 'react-router-dom'
import { StoreContext } from './../../components/context/StoreContext';
import axios from 'axios';

const Verify = () => {

    const [searchParams] = useSearchParams();
    const success = searchParams.get("success")
    const orderId = searchParams.get("orderId")
    const provider = searchParams.get("provider")
    const txRef = searchParams.get("tx_ref") || searchParams.get("trx_ref")
    const mock = searchParams.get("mock")
    const {refreshCart, refreshCatalog, url} = useContext(StoreContext);
    const navigate = useNavigate();

    const verifyPayment = async () =>{
        const response = await axios.post(url+"/api/order/verify",{
            success,
            orderId,
            provider,
            tx_ref: txRef,
            mock,
        });
        if(response.data.success){
            await Promise.all([refreshCart(), refreshCatalog()])
            navigate('/myorders');
        }
        else{
            navigate('/cart')
        }
    }

    useEffect(()=>{
        verifyPayment();
    },[])
   
  return (
    <div className='verify'>
        <div className="spinner"></div>
    </div>
  )
}

export default Verify
